<?php

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
class SettingsPage
{
    private const PAGE_SLUG   = 'tangible-populater';
    private const MENU_TITLE  = 'Tangible Populator';
    private const PAGE_TITLE  = 'Tangible Populator';
    private const CAPABILITY  = 'manage_options';

    public function __construct(
        private readonly SeedingManager $seedingManager,
        private readonly DatabaseReset  $databaseReset,
        private readonly PluginDetector $pluginDetector,
    ) {}

    public function register(): void
    {
        add_menu_page(
            page_title: self::PAGE_TITLE,
            menu_title: self::MENU_TITLE,
            capability: self::CAPABILITY,
            menu_slug:  self::PAGE_SLUG,
            callback:   [$this, 'render'],
            icon_url:   'dashicons-database-add',
            position:   80,
        );

        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        $baseUrl = plugin_dir_url(TANGIBLE_POPULATER_FILE);

        wp_enqueue_style(
            'tangible-populater-admin',
            $baseUrl . 'assets/admin.css',
            [],
            TANGIBLE_POPULATER_VERSION
        );

        wp_enqueue_script(
            'tangible-populater-admin',
            $baseUrl . 'assets/admin.js',
            ['wp-api-fetch'],
            TANGIBLE_POPULATER_VERSION,
            true
        );

        $activeProcess   = $this->seedingManager->getActiveProcess();
        $defaultPassword = $this->getDefaultUserPassword();
        $defaultTab      = $this->defaultActiveTab();

        $plugins = $this->seedingManager->getSupportedPlugins();

        wp_localize_script('tangible-populater-admin', 'tangiblePopulater', [
            'restUrl'         => rest_url('tangible-populater/v1'),
            'nonce'           => wp_create_nonce('wp_rest'),
            'plugins'         => $plugins,
            'activeProcess'   => $activeProcess?->toArray(),
            'defaultPassword' => $defaultPassword,
            'defaultTab'      => $defaultTab,
            'groupsSupported' => array_map(
                fn($plugin) => \Tangible\Populater\LMS\LmsRegistry::supportsGroups($plugin['slug']),
                $plugins
            ),
            'inactiveTabTitle' => __(
                'This LMS plugin is not active. Activate it in Plugins before seeding.',
                'tangible-populater',
            ),
            'seedDefaults'    => array_combine(
                array_column($plugins, 'slug'),
                array_map(
                    fn($plugin) => \Tangible\Populater\LMS\LmsRegistry::getDefaultConfig($plugin['slug']),
                    $plugins
                )
            ),
        ]);
    }

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Insufficient permissions.', 'tangible-populater'));
        }

        $activePlugins      = $this->pluginDetector->getActivePlugins();
        $allowDatabaseReset = $this->databaseReset->isSafeEnvironment();
        $defaultTab         = $this->defaultActiveTab();
        $pluginStates       = $this->pluginStatesBySlug();

        ?>
        <div class="wrap" id="tangible-populater-app">
            <h1><?php echo esc_html(self::PAGE_TITLE); ?></h1>

            <?php if (empty($activePlugins)) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('No supported LMS plugins are currently active. Please activate a supported LMS plugin.', 'tangible-populater'); ?></p>
                </div>
            <?php endif; ?>

            <div class="card tp-seed-card">
                <h2><?php esc_html_e('Seed Content', 'tangible-populater'); ?></h2>

                <nav class="nav-tab-wrapper tp-seed-tabs" aria-label="<?php esc_attr_e('LMS generator', 'tangible-populater'); ?>">
                    <?php foreach ($this->tabDefinitions() as $slug => $tab) :
                        $isActive = (bool) ($pluginStates[$slug]['active'] ?? false);
                        $tabTitle = $isActive
                            ? ''
                            : sprintf(
                                /* translators: %s: LMS plugin name */
                                __('"%s" is not active. Activate the plugin before seeding.', 'tangible-populater'),
                                (string) ($pluginStates[$slug]['name'] ?? $tab['label']),
                            );
                        ?>
                        <a
                            href="#tp-panel-<?php echo esc_attr($slug); ?>"
                            class="nav-tab<?php echo $slug === $defaultTab ? ' nav-tab-active' : ''; ?><?php echo $isActive ? '' : ' nav-tab--inactive'; ?>"
                            data-tab="<?php echo esc_attr($slug); ?>"
                            data-active="<?php echo $isActive ? '1' : '0'; ?>"
                            id="tp-tab-<?php echo esc_attr($slug); ?>"
                            <?php echo $isActive ? '' : ' aria-disabled="true" tabindex="-1"'; ?>
                            <?php echo $tabTitle !== '' ? ' title="' . esc_attr($tabTitle) . '"' : ''; ?>
                        ><?php echo esc_html($tab['label']); ?></a>
                    <?php endforeach; ?>
                </nav>

                <?php foreach ($this->tabDefinitions() as $slug => $tab) : ?>
                    <div
                        class="tp-seed-panel"
                        id="tp-panel-<?php echo esc_attr($slug); ?>"
                        data-panel="<?php echo esc_attr($slug); ?>"
                        <?php echo $slug !== $defaultTab ? 'hidden' : ''; ?>
                    >
                        <?php if (!empty($tab['description'])) : ?>
                            <p class="description tp-panel-description"><?php echo esc_html($tab['description']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <table class="form-table tp-seed-fields" role="presentation">
                    <?php $this->renderCoursesField(); ?>
                    <?php 
                    foreach (\Tangible\Populater\LMS\LmsRegistry::getPlugins() as $slug => $plugin) {
                        $renderer = \Tangible\Populater\LMS\LmsRegistry::getFieldsRenderer($slug);
                        if ($renderer) {
                            $renderer();
                        }
                        $this->renderGroupsField($slug);
                    }
                    ?>
                    <?php $this->renderAssessmentAndUserFields(); ?>
                </table>

                <p>
                    <button type="button" id="tp-start-btn" class="button button-primary"><?php esc_html_e('Start Seeding', 'tangible-populater'); ?></button>
                    <button type="button" id="tp-cancel-btn" class="button" style="display:none;"><?php esc_html_e('Cancel', 'tangible-populater'); ?></button>
                </p>

                <div id="tp-progress-wrap" style="display:none; margin-top:12px;">
                    <progress id="tp-progress-bar" value="0" max="100" style="width:100%;"></progress>
                    <p id="tp-progress-text"></p>
                </div>
            </div>

            <div class="card" id="tp-log-card" style="max-width:700px; margin-top:16px; display:none;">
                <h2><?php esc_html_e('Logs', 'tangible-populater'); ?></h2>
                <pre id="tp-log-output" style="max-height:300px; overflow-y:auto; background:#f6f7f7; padding:8px; font-size:12px;"></pre>
            </div>

            <div class="card" style="max-width:700px; margin-top:16px; border-left:4px solid #d63638;">
                <h2 style="color:#d63638;"><?php esc_html_e('⚠ Reset Database', 'tangible-populater'); ?></h2>
                <p><?php esc_html_e('This removes seeded posts, non-admin users, plugin/LMS data, custom site roles, and non-core options. Administrator accounts, active plugins, and the active theme are preserved.', 'tangible-populater'); ?></p>
                <?php if (!$allowDatabaseReset) : ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e('Database reset is disabled on this site. Define TANGIBLE_POPULATER_ALLOW_DB_RESET as true in wp-config.php, or use the tangible_populater_allow_database_reset filter to enable it.', 'tangible-populater'); ?></p>
                    </div>
                <?php endif; ?>
                <button
                    type="button"
                    id="tp-reset-btn"
                    class="button"
                    style="background:#d63638; color:#fff; border-color:#d63638;"
                    <?php disabled(!$allowDatabaseReset); ?>
                ><?php esc_html_e('Reset Database', 'tangible-populater'); ?></button>
            </div>
        </div>
        <?php
    }

    private function defaultActiveTab(): string
    {
        foreach ($this->seedingManager->getSupportedPlugins() as $plugin) {
            if ($plugin['active']) {
                return (string) $plugin['slug'];
            }
        }

        $plugins = \Tangible\Populater\LMS\LmsRegistry::getPlugins();
        return !empty($plugins) ? (string) array_key_first($plugins) : 'default';
    }

    /**
     * @return array<string, array{slug: string, name: string, active: bool}>
     */
    private function pluginStatesBySlug(): array
    {
        $states = [];

        foreach ($this->seedingManager->getSupportedPlugins() as $plugin) {
            $states[(string) $plugin['slug']] = $plugin;
        }

        return $states;
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    private function tabDefinitions(): array
    {
        return \Tangible\Populater\LMS\LmsRegistry::getPlugins();
    }

    private function renderCoursesField(): void
    {
        ?>
        <tr class="tp-field-row tp-field-row--shared">
            <th scope="row"><label for="tp-courses"><?php esc_html_e('Courses', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-courses" name="courses" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_COURSES); ?>" min="0" max="500" class="small-text"></td>
        </tr>
        <?php
    }

    private function renderGroupsField(string $slug): void
    {
        if (!LmsGroupsCapability::supports($slug)) {
            return;
        }

        $inputId = "tp-groups-{$slug}";
        ?>
        <tr class="tp-field-row" data-tp-tab="<?php echo esc_attr($slug); ?>">
            <th scope="row"><label for="<?php echo esc_attr($inputId); ?>"><?php esc_html_e('Create Groups', 'tangible-populater'); ?></label></th>
            <td>
                <input type="number" id="<?php echo esc_attr($inputId); ?>" data-tp-field="groups" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_GROUPS); ?>" min="0" max="100" class="small-text">
                <p class="description"><?php esc_html_e('When set, users and courses are split evenly across groups. Each group gets a group admin.', 'tangible-populater'); ?></p>
            </td>
        </tr>
        <?php
    }

    private function renderAssessmentAndUserFields(): void
    {
        ?>
        <tr class="tp-field-row tp-field-row--shared">
            <th scope="row"><label for="tp-questions"><?php esc_html_e('Questions per Quiz', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-questions" name="questions_per_quiz" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ); ?>" min="0" max="100" class="small-text"></td>
        </tr>
        <tr class="tp-field-row tp-field-row--shared">
            <th scope="row"><label for="tp-users"><?php esc_html_e('Users', 'tangible-populater'); ?></label></th>
            <td><input type="number" id="tp-users" name="users" value="<?php echo esc_attr((string) SeedConfig::DEFAULT_USERS); ?>" min="0" max="1000" class="small-text"></td>
        </tr>
        <tr class="tp-field-row tp-field-row--shared">
            <th scope="row"><label for="tp-user-password"><?php esc_html_e('User Password', 'tangible-populater'); ?></label></th>
            <td>
                <div class="tp-password-field">
                    <input type="text" id="tp-user-password" name="user_password" class="regular-text" autocomplete="off" value="<?php echo esc_attr($this->getDefaultUserPassword()); ?>">
                    <button type="button" id="tp-password-copy" class="button"><?php esc_html_e('Copy', 'tangible-populater'); ?></button>
                </div>
                <p class="description"><?php esc_html_e('Shared password for all seeded users (students and group admins).', 'tangible-populater'); ?></p>
            </td>
        </tr>
        <?php
    }

    private function getDefaultUserPassword(): string
    {
        return SeedConfig::DEFAULT_USER_PASSWORD;
    }
}
