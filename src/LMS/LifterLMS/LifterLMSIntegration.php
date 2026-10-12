<?php
/**
 * Lifter L M S Integration.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\LMS\LmsSettingsTab;
use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Support\LmsGroupsCapability;

/**
 * LifterLMS integration for Tangible Populater.
 */
class LifterLMSIntegration extends LmsSettingsTab {

	/**
	 * Returns the LifterLMS settings tab slug.
	 *
	 * @return string The slug "lifterlms".
	 */
	public function getSlug(): string {
		return 'lifterlms';
	}

	/**
	 * Returns the display label for the LifterLMS tab.
	 *
	 * @return string The translated tab label.
	 */
	public function getLabel(): string {
		return __( 'LifterLMS', 'tangible-populater' );
	}

	/**
	 * Returns a description of the LifterLMS content hierarchy.
	 *
	 * @return string The translated structure description.
	 */
	public function getDescription(): string {
		return __( 'Course → sections → lessons → quiz. One quiz per section.', 'tangible-populater' );
	}

	/**
	 * Returns the default LifterLMS seeding configuration.
	 *
	 * @return array<string, mixed> Default counts keyed by SeedConfig field name.
	 */
	public function getDefaultConfig(): array {
		return array(
			'courses'           => SeedConfig::DEFAULT_COURSES,
			'sectionsPerCourse' => SeedConfig::DEFAULT_SECTIONS_PER_COURSE,
			'lessonsPerSection' => SeedConfig::DEFAULT_LESSONS_PER_SECTION,
			'quizzesPerSection' => SeedConfig::DEFAULT_QUIZZES_PER_SECTION,
			'questionsPerQuiz'  => SeedConfig::DEFAULT_QUESTIONS_PER_QUIZ,
			'users'             => SeedConfig::DEFAULT_USERS,
			'groups'            => SeedConfig::DEFAULT_GROUPS,
		);
	}

	/**
	 * Tells whether LifterLMS groups can be seeded.
	 *
	 * @return bool True when the groups capability is available for LifterLMS.
	 */
	public function supportsGroups(): bool {
		return LmsGroupsCapability::supports( 'lifterlms' );
	}

	/**
	 * Renders the LifterLMS sections, lessons and quizzes number inputs.
	 *
	 * @return void
	 */
	public function renderFields(): void {
		?>
		<tr class="tp-field-row" data-tp-tab="lifterlms">
			<th scope="row"><label for="tp-llms-sections"><?php esc_html_e( 'Sections per Course', 'tangible-populater' ); ?></label></th>
			<td><input type="number" id="tp-llms-sections" data-tp-field="sections_per_course" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_SECTIONS_PER_COURSE ); ?>" min="0" max="50" class="small-text"></td>
		</tr>
		<tr class="tp-field-row" data-tp-tab="lifterlms">
			<th scope="row"><label for="tp-llms-lessons"><?php esc_html_e( 'Lessons per Section', 'tangible-populater' ); ?></label></th>
			<td><input type="number" id="tp-llms-lessons" data-tp-field="lessons_per_section" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_LESSONS_PER_SECTION ); ?>" min="0" max="100" class="small-text"></td>
		</tr>
		<tr class="tp-field-row" data-tp-tab="lifterlms">
			<th scope="row"><label for="tp-llms-quizzes"><?php esc_html_e( 'Quizzes per Section', 'tangible-populater' ); ?></label></th>
			<td><input type="number" id="tp-llms-quizzes" data-tp-field="quizzes_per_section" value="<?php echo esc_attr( (string) SeedConfig::DEFAULT_QUIZZES_PER_SECTION ); ?>" min="0" max="50" class="small-text"></td>
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
	 * @return string The slug "lifterlms".
	 */
	private static function getSlugStatic(): string {
		return 'lifterlms';
	}
}
