<?php
/**
 * Tangible L M S Integration.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\LMS\LmsSettingsTab;
use Tangible\Populater\Seeding\SeedConfig;

/**
 * Tangible LMS integration for Tangible Populater.
 */
class TangibleLMSIntegration extends LmsSettingsTab {

	/**
	 * Returns the Tangible LMS settings tab slug.
	 *
	 * @return string The slug "tangible-lms".
	 */
	public function getSlug(): string {
		return 'tangible-lms';
	}

	/**
	 * Returns the display label for the Tangible LMS tab.
	 *
	 * @return string The translated tab label.
	 */
	public function getLabel(): string {
		return __( 'Tangible LMS', 'tangible-populater' );
	}

	/**
	 * Returns a description of the Tangible LMS content hierarchy.
	 *
	 * @return string The translated structure description.
	 */
	public function getDescription(): string {
		return __( 'Course → modules → lessons → quiz. One quiz per module.', 'tangible-populater' );
	}

	/**
	 * Returns the default Tangible LMS seeding configuration.
	 *
	 * @return array<string, mixed> Default counts keyed by SeedConfig field name.
	 */
	public function getDefaultConfig(): array {
		return array(
			'courses'          => SeedConfig::DEFAULT_COURSES,
			'modulesPerCourse' => SeedConfig::DEFAULT_MODULES_PER_COURSE,
			'lessonsPerCourse' => SeedConfig::DEFAULT_LESSONS_PER_COURSE,
			'quizzesPerModule' => SeedConfig::DEFAULT_QUIZZES_PER_SECTION,
			'questionsPerQuiz' => SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ,
			'users'            => SeedConfig::DEFAULT_USERS,
			'groups'           => SeedConfig::DEFAULT_GROUPS,
		);
	}

	/**
	 * Tells whether Tangible LMS supports groups.
	 *
	 * @return bool Always false.
	 */
	public function supportsGroups(): bool {
		return false;
	}

	/**
	 * Renders the Tangible LMS modules, lessons and quizzes number inputs.
	 *
	 * @return void
	 */
	public function renderFields(): void {
		?>
		<tr class="tp-field-row" data-tp-tab="tangible-lms">
			<th scope="row"><label for="tp-tgl-modules"><?php esc_html_e( 'Modules per Course', 'tangible-populater' ); ?></label></th>
			<td><input type="number" id="tp-tgl-modules" data-tp-field="modules_per_course" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_MODULES_PER_COURSE ); ?>" min="0" max="50" class="small-text"></td>
		</tr>
		<tr class="tp-field-row" data-tp-tab="tangible-lms">
			<th scope="row"><label for="tp-tgl-lessons"><?php esc_html_e( 'Lessons per Course', 'tangible-populater' ); ?></label></th>
			<td><input type="number" id="tp-tgl-lessons" data-tp-field="lessons_per_course" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_LESSONS_PER_COURSE ); ?>" min="0" max="100" class="small-text"></td>
		</tr>
		<tr class="tp-field-row" data-tp-tab="tangible-lms">
			<th scope="row"><label for="tp-tgl-quizzes"><?php esc_html_e( 'Quizzes per Module', 'tangible-populater' ); ?></label></th>
			<td><input type="number" id="tp-tgl-quizzes" data-tp-field="quizzes_per_section" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_QUIZZES_PER_SECTION ); ?>" min="0" max="50" class="small-text"></td>
		</tr>
		<?php
	}

	/**
	 * Registers this tab with the tangible_populater_lms_tabs filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter(
			'tangible_populater_lms_tabs',
			function ( array $tabs ) {
				$tabs[ self::getSlugStatic() ] = new self();
				return $tabs;
			}
		);
	}

	/**
	 * Returns the slug used as the tab key when registering.
	 *
	 * @return string The slug "tangible-lms".
	 */
	private static function getSlugStatic(): string {
		return 'tangible-lms';
	}
}

