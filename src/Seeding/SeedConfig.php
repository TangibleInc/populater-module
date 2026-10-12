<?php
/**
 * Seed config.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Validated seeding configuration passed to queue builders and processes.
 */
final class SeedConfig {

	public const DEFAULT_PLUGIN              = 'learndash';
	public const DEFAULT_COURSES             = 5;
	public const DEFAULT_LESSONS_PER_COURSE  = 5;
	public const DEFAULT_LESSONS_PER_SECTION = 10;
	public const DEFAULT_QUIZZES_PER_SECTION = 1;
	public const DEFAULT_QUIZZES_PER_LESSON  = 1;
	public const DEFAULT_QUESTIONS_PER_QUIZ  = 10;
	public const DEFAULT_TOPICS_PER_LESSON   = 10;
	public const DEFAULT_SECTIONS_PER_COURSE = 5;
	public const DEFAULT_MODULES_PER_COURSE  = 1;
	public const DEFAULT_USERS               = 100;
	public const DEFAULT_GROUPS              = 1;
	public const DEFAULT_USER_PASSWORD       = 'StressTest#2026';

	/**
	 * Creates a seeding configuration from already validated values.
	 *
	 * @param string      $plugin            Slug of the LMS plugin to seed.
	 * @param int         $courses           Number of courses to create.
	 * @param int         $lessonsPerCourse  Number of lessons per course.
	 * @param int         $quizzesPerSection Number of quizzes per section, or per lesson for LearnDash.
	 * @param int         $questionsPerQuiz  Number of questions per quiz.
	 * @param int         $topicsPerLesson   Number of topics per lesson.
	 * @param int         $sectionsPerCourse Number of sections per course.
	 * @param int         $lessonsPerSection Number of lessons per section.
	 * @param int         $modulesPerCourse  Number of modules per course.
	 * @param int         $users             Number of users to create.
	 * @param int         $groups            Number of groups to create.
	 * @param string|null $userPassword      Password for created users, or null to use the default.
	 * @param bool        $completeCourses   Whether seeded students should complete their courses.
	 */
	public function __construct(
		public readonly string $plugin,
		public readonly int $courses,
		public readonly int $lessonsPerCourse,
		public readonly int $quizzesPerSection,
		public readonly int $questionsPerQuiz,
		public readonly int $topicsPerLesson,
		public readonly int $sectionsPerCourse,
		public readonly int $lessonsPerSection,
		public readonly int $modulesPerCourse,
		public readonly int $users,
		public readonly int $groups = 0,
		public readonly ?string $userPassword = null,
		public readonly bool $completeCourses = false,
	) {}

	/**
	 * Builds a validated configuration from raw request or CLI input, clamping counts to zero or above.
	 *
	 * @param array<string, mixed> $config Raw configuration keyed by option name; "plugin" is required.
	 * @return self Validated configuration.
	 * @throws \InvalidArgumentException If the "plugin" key is missing or empty.
	 */
	public static function fromArray( array $config ): self {
		$plugin = (string) ( $config['plugin'] ?? '' );

		if ( '' === $plugin ) {
			throw new \InvalidArgumentException( 'Config key "plugin" is required.' );
		}

		$counts = self::resolveStructureCounts( $plugin, $config );

		return new self(
			plugin: $plugin,
			courses: max( 0, (int) ( $config['courses'] ?? 0 ) ),
			lessonsPerCourse: $counts['lessonsPerCourse'],
			quizzesPerSection: $counts['quizzesPerSection'],
			questionsPerQuiz: max( 0, (int) ( $config['questions_per_quiz'] ?? self::DEFAULT_QUESTIONS_PER_QUIZ ) ),
			topicsPerLesson: $counts['topicsPerLesson'],
			sectionsPerCourse: $counts['sectionsPerCourse'] ?? 0,
			lessonsPerSection: $counts['lessonsPerSection'] ?? 0,
			modulesPerCourse: max( 0, (int) ( $config['modules_per_course'] ?? self::DEFAULT_MODULES_PER_COURSE ) ),
			users: max( 0, (int) ( $config['users'] ?? 0 ) ),
			groups: max( 0, (int) ( $config['groups'] ?? 0 ) ),
			userPassword: self::normalizePassword( $config['user_password'] ?? null ),
			completeCourses: (bool) ( $config['complete_courses'] ?? false ),
		);
	}

	/**
	 * Resolves the plugin-specific content structure counts, applying defaults for missing values.
	 *
	 * @param string               $plugin Slug of the LMS plugin.
	 * @param array<string, mixed> $config Raw configuration keyed by option name.
	 * @return array{lessonsPerCourse: int, topicsPerLesson: int, sectionsPerCourse?: int, lessonsPerSection?: int, quizzesPerSection: int} Resolved counts.
	 */
	private static function resolveStructureCounts( string $plugin, array $config ): array {
		return match ( $plugin ) {
			'learndash' => array(
				'lessonsPerCourse'  => max( 0, (int) ( $config['lessons_per_course'] ?? self::DEFAULT_LESSONS_PER_COURSE ) ),
				'topicsPerLesson'   => max( 0, (int) ( $config['topics_per_lesson'] ?? self::DEFAULT_TOPICS_PER_LESSON ) ),
				'quizzesPerSection' => max( 0, (int) ( $config['quizzes_per_lesson'] ?? $config['quizzes_per_section'] ?? self::DEFAULT_QUIZZES_PER_LESSON ) ),
			),
			'lifterlms' => self::resolveLifterStructureCounts( $config ),
			default     => array(
				'lessonsPerCourse'  => max( 0, (int) ( $config['lessons_per_course'] ?? self::DEFAULT_LESSONS_PER_COURSE ) ),
				'topicsPerLesson'   => 0,
				'sectionsPerCourse' => 0,
				'lessonsPerSection' => 0,
				'quizzesPerSection' => max( 0, (int) ( $config['quizzes_per_section'] ?? $config['quizzes_per_module'] ?? self::DEFAULT_QUIZZES_PER_SECTION ) ),
			),
		};
	}

	/**
	 * Resolves LifterLMS structure counts, deriving lessons per course from sections and lessons per section when both are set.
	 *
	 * @param array<string, mixed> $config Raw configuration keyed by option name.
	 * @return array{lessonsPerCourse: int, topicsPerLesson: int, sectionsPerCourse: int, lessonsPerSection: int, quizzesPerSection: int} Resolved counts.
	 */
	private static function resolveLifterStructureCounts( array $config ): array {
		$sectionsPerCourse = max( 0, (int) ( $config['sections_per_course'] ?? self::DEFAULT_SECTIONS_PER_COURSE ) );
		$lessonsPerSection = max( 0, (int) ( $config['lessons_per_section'] ?? self::DEFAULT_LESSONS_PER_SECTION ) );
		$lessonsPerCourse  = max( 0, (int) ( $config['lessons_per_course'] ?? 0 ) );

		if ( $lessonsPerSection > 0 && $sectionsPerCourse > 0 ) {
			$lessonsPerCourse = $sectionsPerCourse * $lessonsPerSection;
		}

		return array(
			'lessonsPerCourse'  => $lessonsPerCourse,
			'topicsPerLesson'   => 0,
			'sectionsPerCourse' => $sectionsPerCourse,
			'lessonsPerSection' => $lessonsPerSection,
			'quizzesPerSection' => max( 0, (int) ( $config['quizzes_per_section'] ?? self::DEFAULT_QUIZZES_PER_SECTION ) ),
		);
	}

	/**
	 * Trims a password and converts non-strings and blank values to null.
	 *
	 * @param mixed $password Raw password value.
	 * @return string|null Trimmed password, or null if it is not a non-empty string.
	 */
	private static function normalizePassword( mixed $password ): ?string {
		if ( ! is_string( $password ) ) {
			return null;
		}

		$password = trim( $password );

		return '' !== $password ? $password : null;
	}

	/**
	 * Converts the configuration back to the raw option keys used by the given plugin.
	 *
	 * @return array<string, mixed> Configuration keyed by option name, limited to the keys relevant to the plugin.
	 */
	public function toArray(): array {
		$base = array(
			'plugin'             => $this->plugin,
			'courses'            => $this->courses,
			'questions_per_quiz' => $this->questionsPerQuiz,
			'users'              => $this->users,
			'groups'             => $this->groups,
			'user_password'      => $this->userPassword,
		);

		$base['complete_courses'] = $this->completeCourses;

		return match ( $this->plugin ) {
			'learndash' => array_merge(
				$base,
				array(
					'lessons_per_course' => $this->lessonsPerCourse,
					'topics_per_lesson'  => $this->topicsPerLesson,
					'quizzes_per_lesson' => $this->quizzesPerSection,
				)
			),
			'lifterlms' => array_merge(
				$base,
				array(
					'sections_per_course' => $this->sectionsPerCourse,
					'lessons_per_section' => $this->lessonsPerSection,
					'lessons_per_course'  => $this->lessonsPerCourse,
					'quizzes_per_section' => $this->quizzesPerSection,
				)
			),
			'tangible-lms' => array_merge(
				$base,
				array(
					'modules_per_course'  => $this->modulesPerCourse,
					'lessons_per_course'  => $this->lessonsPerCourse,
					'quizzes_per_section' => $this->quizzesPerSection,
				)
			),
			default => array_merge(
				$base,
				array(
					'lessons_per_course'  => $this->lessonsPerCourse,
					'quizzes_per_section' => $this->quizzesPerSection,
				)
			),
		};//end match
	}
}
