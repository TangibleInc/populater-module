<?php
/**
 * Settings page.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Admin;

use Tangible\Populater\Database\DatabaseReset;
use Tangible\Populater\PluginDetector;
use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Seeding\SeedingManager;
use Tangible\Populater\Support\LmsGroupsCapability;

/**
 * Registers and renders the "Tangible Populator" admin settings page.
 */
class SettingsPage {

	private const PAGE_SLUG  = 'tangible-populater';
	private const MENU_TITLE = 'Tangible Populator';
	private const PAGE_TITLE = 'Tangible Populator';
	private const CAPABILITY = 'manage_options';

	/**
	 * Creates the settings page.
	 *
	 * @param SeedingManager $seedingManager Manager used to read supported plugins and the active process.
	 * @param DatabaseReset  $databaseReset  Reset service used to check whether a reset is allowed.
	 * @param PluginDetector $pluginDetector Detector of the active LMS plugins.
	 */
	public function __construct(
		private readonly SeedingManager $seedingManager,
		private readonly DatabaseReset $databaseReset,
		private readonly PluginDetector $pluginDetector,
	) {}

	/**
	 * Adds the top-level admin menu page and hooks the asset loader.
	 *
	 * @return void
	 */
	public function register(): void {
		add_menu_page(
			page_title: self::PAGE_TITLE,
			menu_title: self::MENU_TITLE,
			capability: self::CAPABILITY,
			menu_slug:  self::PAGE_SLUG,
			callback:   array( $this, 'render' ),
			icon_url:   'dashicons-database-add',
			position:   80,
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	/**
	 * Enqueues the admin script and style and passes runtime settings to the script on the plugin page only.
	 *
	 * @param string $hookSuffix Hook suffix of the current admin page.
	 * @return void
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( 'toplevel_page_' !== $hookSuffix . self::PAGE_SLUG ) {
			return;
		}

		$baseUrl = plugin_dir_url( TANGIBLE_POPULATER_FILE );

		wp_enqueue_style(
			'tangible-populater-admin',
			$baseUrl . 'assets/admin.css',
			array(),
			TANGIBLE_POPULATER_VERSION
		);

		wp_enqueue_script(
			'tangible-populater-admin',
			$baseUrl . 'assets/admin.js',
			array( 'wp-api-fetch' ),
			TANGIBLE_POPULATER_VERSION,
			true
		);

		$activeProcess   = $this->seedingManager->getActiveProcess();
		$defaultPassword = $this->getDefaultUserPassword();
		$defaultTab      = $this->defaultActiveTab();

		$plugins = $this->seedingManager->getSupportedPlugins();

		wp_localize_script(
			'tangible-populater-admin',
			'tangiblePopulater',
			array(
				'restUrl'          => rest_url( 'tangible-populater/v1' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'plugins'          => $plugins,
				'activeProcess'    => $activeProcess?->toArray(),
				'defaultPassword'  => $defaultPassword,
				'defaultTab'       => $defaultTab,
				'groupsSupported'  => array_combine(
					array_column( $plugins, 'slug' ),
					array_map(
						fn( $plugin ) => \Tangible\Populater\LMS\LmsRegistry::supportsGroups( $plugin['slug'] ),
						$plugins
					)
				),
				'inactiveTabTitle' => __(
					'This LMS plugin is not active. Activate it in Plugins before seeding.',
					'tangible-populater',
				),
				'seedDefaults'     => array_combine(
					array_column( $plugins, 'slug' ),
					array_map(
						fn( $plugin ) => \Tangible\Populater\LMS\LmsRegistry::getDefaultConfig( $plugin['slug'] ),
						$plugins
					)
				),
			)
		);
	}

	/**
	 * Outputs the settings page markup for users who can manage options.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'tangible-populater' ) );
		}

		$activePlugins      = $this->pluginDetector->getActivePlugins();
		$allowDatabaseReset = $this->databaseReset->isSafeEnvironment();
		$defaultTab         = $this->defaultActiveTab();
		$pluginStates       = $this->pluginStatesBySlug();

		?>
		<div class="wrap" id="tangible-populater-app">
			<h1><?php echo esc_html( self::PAGE_TITLE ); ?></h1>

			<?php if ( empty( $activePlugins ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'No supported LMS plugins are currently active. Please activate a supported LMS plugin.', 'tangible-populater' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card tp-seed-card">
				<h2><?php esc_html_e( 'Seed Content', 'tangible-populater' ); ?></h2>

				<nav class="nav-tab-wrapper tp-seed-tabs" aria-label="<?php esc_attr_e( 'LMS generator', 'tangible-populater' ); ?>">
					<?php
					foreach ( $this->tabDefinitions() as $slug => $tab ) :
						$isActive = (bool) ( $pluginStates[ $slug ]['active'] ?? false );
						$tabTitle = $isActive
							? ''
							: sprintf(
								/* translators: %s: LMS plugin name */
								__( '"%s" is not active. Activate the plugin before seeding.', 'tangible-populater' ),
								(string) ( $pluginStates[ $slug ]['name'] ?? $tab['label'] ),
							);
						?>
						<a
							href="#tp-panel-<?php echo esc_attr( $slug ); ?>"
							class="nav-tab<?php echo $slug === $defaultTab ? ' nav-tab-active' : ''; ?><?php echo $isActive ? '' : ' nav-tab--inactive'; ?>"
							data-tab="<?php echo esc_attr( $slug ); ?>"
							data-active="<?php echo $isActive ? '1' : '0'; ?>"
							id="tp-tab-<?php echo esc_attr( $slug ); ?>"
							<?php echo $isActive ? '' : ' aria-disabled="true" tabindex="-1"'; ?>
							<?php echo '' !== $tabTitle ? ' title="' . esc_attr( $tabTitle ) . '"' : ''; ?>
						><?php echo esc_html( $tab['label'] ); ?></a>
					<?php endforeach; ?>
				</nav>

				<?php foreach ( $this->tabDefinitions() as $slug => $tab ) : ?>
					<div
						class="tp-seed-panel"
						id="tp-panel-<?php echo esc_attr( $slug ); ?>"
						data-panel="<?php echo esc_attr( $slug ); ?>"
						<?php echo $slug !== $defaultTab ? 'hidden' : ''; ?>
					>
						<?php if ( ! empty( $tab['description'] ) ) : ?>
							<p class="description tp-panel-description"><?php echo esc_html( $tab['description'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<table class="form-table tp-seed-fields" role="presentation">
					<?php $this->renderCoursesField(); ?>
					<?php
					foreach ( \Tangible\Populater\LMS\LmsRegistry::getPlugins() as $slug => $plugin ) {
						$renderer = \Tangible\Populater\LMS\LmsRegistry::getFieldsRenderer( $slug );
						if ( $renderer ) {
							$renderer();
						}
						$this->renderGroupsField( $slug );
					}
					?>
					<?php $this->renderAssessmentAndUserFields(); ?>
				</table>

				<p>
					<button type="button" id="tp-start-btn" class="button button-primary"><?php esc_html_e( 'Start Seeding', 'tangible-populater' ); ?></button>
					<button type="button" id="tp-cancel-btn" class="button" style="display:none;"><?php esc_html_e( 'Cancel', 'tangible-populater' ); ?></button>
				</p>

				<div id="tp-progress-wrap" style="display:none; margin-top:12px;">
					<progress id="tp-progress-bar" value="0" max="100" style="width:100%;"></progress>
					<p id="tp-progress-text"></p>
				</div>
			</div>

			<div class="card" id="tp-log-card" style="max-width:700px; margin-top:16px; display:none;">
				<h2><?php esc_html_e( 'Logs', 'tangible-populater' ); ?></h2>
				<pre id="tp-log-output" style="max-height:300px; overflow-y:auto; background:#f6f7f7; padding:8px; font-size:12px;"></pre>
			</div>

			<div class="card" style="max-width:700px; margin-top:16px; border-left:4px solid #d63638;">
				<h2 style="color:#d63638;"><?php esc_html_e( '⚠ Reset Database', 'tangible-populater' ); ?></h2>
				<p><?php esc_html_e( 'This removes seeded posts, non-admin users, plugin/LMS data, custom site roles, and non-core options. Administrator accounts, active plugins, and the active theme are preserved.', 'tangible-populater' ); ?></p>
				<?php if ( ! $allowDatabaseReset ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'Database reset is disabled on this site. Define TANGIBLE_POPULATER_ALLOW_DB_RESET as true in wp-config.php, or use the tangible_populater_allow_database_reset filter to enable it.', 'tangible-populater' ); ?></p>
					</div>
				<?php endif; ?>
				<button
					type="button"
					id="tp-reset-btn"
					class="button"
					style="background:#d63638; color:#fff; border-color:#d63638;"
					<?php disabled( ! $allowDatabaseReset ); ?>
				><?php esc_html_e( 'Reset Database', 'tangible-populater' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Picks the first active LMS plugin slug as the default tab, falling back to the first registered plugin.
	 *
	 * @return string Plugin slug of the default tab, or "default" if no plugin is registered.
	 */
	private function defaultActiveTab(): string {
		foreach ( $this->seedingManager->getSupportedPlugins() as $plugin ) {
			if ( $plugin['active'] ) {
				return (string) $plugin['slug'];
			}
		}

		$plugins = \Tangible\Populater\LMS\LmsRegistry::getPlugins();
		return ! empty( $plugins ) ? (string) array_key_first( $plugins ) : 'default';
	}

	/**
	 * Indexes the supported plugins by slug.
	 *
	 * @return array<string, array{slug: string, name: string, active: bool}> Plugin states keyed by plugin slug.
	 */
	private function pluginStatesBySlug(): array {
		$states = array();

		foreach ( $this->seedingManager->getSupportedPlugins() as $plugin ) {
			$states[ (string) $plugin['slug'] ] = $plugin;
		}

		return $states;
	}

	/**
	 * Gets the registered LMS plugins used to build the tabs.
	 *
	 * @return array<string, array{label: string, description: string}> Tab labels and descriptions keyed by plugin slug.
	 */
	private function tabDefinitions(): array {
		return \Tangible\Populater\LMS\LmsRegistry::getPlugins();
	}

	/**
	 * Outputs the table row for the shared courses count field.
	 *
	 * @return void
	 */
	private function renderCoursesField(): void {
		?>
		<tr class="tp-field-row tp-field-row--shared">
			<th scope="row"><label for="tp-courses"><?php esc_html_e( 'Courses', 'tangible-populater' ); ?></label></th>
			<td><input type="number" id="tp-courses" name="courses" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_COURSES ); ?>" min="0" max="100000" class="regular-text" style="width:8em;"></td>
		</tr>
		<?php
	}

	/**
	 * Outputs the table row for the groups count field when the plugin supports groups.
	 *
	 * @param string $slug Plugin slug the field belongs to.
	 * @return void
	 */
	private function renderGroupsField( string $slug ): void {
		if ( ! LmsGroupsCapability::supports( $slug ) ) {
			return;
		}

		$inputId = "tp-groups-{$slug}";
		?>
		<tr class="tp-field-row" data-tp-tab="<?php echo esc_attr( $slug ); ?>">
			<th scope="row"><label for="<?php echo esc_attr( $inputId ); ?>"><?php esc_html_e( 'Create Groups', 'tangible-populater' ); ?></label></th>
			<td>
				<input type="number" id="<?php echo esc_attr( $inputId ); ?>" data-tp-field="groups" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_GROUPS ); ?>" min="0" max="100" class="small-text">
				<p class="description"><?php esc_html_e( 'When set, users and courses are split evenly across groups. Each group gets a group admin.', 'tangible-populater' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Outputs the table rows for questions per quiz, users, course completion and user password.
	 *
	 * @return void
	 */
	private function renderAssessmentAndUserFields(): void {
		?>
		<tr class="tp-field-row tp-field-row--shared">
			<th scope="row"><label for="tp-questions"><?php esc_html_e( 'Questions per Quiz', 'tangible-populater' ); ?></label></th>
			<td><input type="number" id="tp-questions" name="questions_per_quiz" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ ); ?>" min="0" max="100" class="small-text"></td>
		</tr>
		<tr class="tp-field-row tp-field-row--shared">
			<th scope="row"><label for="tp-users"><?php esc_html_e( 'Users', 'tangible-populater' ); ?></label></th>
			<td><input type="number" id="tp-users" name="users" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_USERS ); ?>" min="0" max="100000" class="regular-text" style="width:8em;"></td>
		</tr>
		<tr class="tp-field-row tp-field-row--shared">
			<th scope="row"><label for="tp-complete-courses"><?php esc_html_e( 'Complete Courses', 'tangible-populater' ); ?></label></th>
			<td>
				<label>
					<input type="checkbox" id="tp-complete-courses" name="complete_courses">
					<?php esc_html_e( 'Simulate students completing all seeded courses', 'tangible-populater' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Enrolls every student in every course, marks lessons complete, records passing quiz attempts, and triggers course completion. Supported for LifterLMS and LearnDash. Use with large datasets to generate realistic report data.', 'tangible-populater' ); ?>
				</p>
			</td>
		</tr>
		<tr class="tp-field-row tp-field-row--shared">
			<th scope="row"><label for="tp-user-password"><?php esc_html_e( 'User Password', 'tangible-populater' ); ?></label></th>
			<td>
				<div class="tp-password-field">
					<input type="text" id="tp-user-password" name="user_password" class="regular-text" autocomplete="off" value="<?php echo esc_attr( $this->getDefaultUserPassword() ); ?>">
					<button type="button" id="tp-password-copy" class="button"><?php esc_html_e( 'Copy', 'tangible-populater' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Shared password for all seeded users (students and group admins).', 'tangible-populater' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Gets the default password prefilled for seeded users.
	 *
	 * @return string Default user password.
	 */
	private function getDefaultUserPassword(): string {
		return SeedConfig::DEFAULT_USER_PASSWORD;
	}
}
