<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Validated seeding configuration passed to queue builders and processes.
 */
final class SeedConfig
{
    public const DEFAULT_PLUGIN              = 'learndash';
    public const DEFAULT_COURSES             = 5;
    public const DEFAULT_LESSONS_PER_COURSE  = 5;
    public const DEFAULT_LESSONS_PER_SECTION = 10;
    public const DEFAULT_QUIZZES_PER_SECTION = 1;
    public const DEFAULT_QUIZZES_PER_LESSON   = 1;
    public const DEFAULT_QUESTIONS_PER_QUIZ  = 10;
    public const DEFAULT_TOPICS_PER_LESSON   = 10;
    public const DEFAULT_SECTIONS_PER_COURSE = 5;
    public const DEFAULT_MODULES_PER_COURSE  = 1;
    public const DEFAULT_USERS               = 100;
    public const DEFAULT_GROUPS              = 1;
    public const DEFAULT_USER_PASSWORD       = 'StressTest#2026';

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
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $plugin = (string) ($config['plugin'] ?? '');

        if ($plugin === '') {
            throw new \InvalidArgumentException('Config key "plugin" is required.');
        }

        $counts = self::resolveStructureCounts($plugin, $config);

        return new self(
            plugin: $plugin,
            courses: max(0, (int) ($config['courses'] ?? 0)),
            lessonsPerCourse: $counts['lessonsPerCourse'],
            quizzesPerSection: $counts['quizzesPerSection'],
            questionsPerQuiz: max(0, (int) ($config['questions_per_quiz'] ?? self::DEFAULT_QUESTIONS_PER_QUIZ)),
            topicsPerLesson: $counts['topicsPerLesson'],
            sectionsPerCourse: $counts['sectionsPerCourse'] ?? 0,
            lessonsPerSection: $counts['lessonsPerSection'] ?? 0,
            modulesPerCourse: max(0, (int) ($config['modules_per_course'] ?? self::DEFAULT_MODULES_PER_COURSE)),
            users: max(0, (int) ($config['users'] ?? 0)),
            groups: max(0, (int) ($config['groups'] ?? 0)),
            userPassword: self::normalizePassword($config['user_password'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $config
     * @return array{
     *   lessonsPerCourse: int,
     *   topicsPerLesson: int,
     *   sectionsPerCourse?: int,
     *   lessonsPerSection?: int,
     *   quizzesPerSection: int
     * }
     */
    private static function resolveStructureCounts(string $plugin, array $config): array
    {
        return match ($plugin) {
            'learndash' => [
                'lessonsPerCourse'  => max(0, (int) ($config['lessons_per_course'] ?? self::DEFAULT_LESSONS_PER_COURSE)),
                'topicsPerLesson'   => max(0, (int) ($config['topics_per_lesson'] ?? self::DEFAULT_TOPICS_PER_LESSON)),
                'quizzesPerSection' => max(0, (int) ($config['quizzes_per_lesson'] ?? $config['quizzes_per_section'] ?? self::DEFAULT_QUIZZES_PER_LESSON)),
            ],
            'lifterlms' => self::resolveLifterStructureCounts($config),
            default     => [
                'lessonsPerCourse'  => max(0, (int) ($config['lessons_per_course'] ?? self::DEFAULT_LESSONS_PER_COURSE)),
                'topicsPerLesson'   => 0,
                'sectionsPerCourse' => 0,
                'lessonsPerSection' => 0,
                'quizzesPerSection' => max(0, (int) ($config['quizzes_per_section'] ?? $config['quizzes_per_module'] ?? self::DEFAULT_QUIZZES_PER_SECTION)),
            ],
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return array{
     *   lessonsPerCourse: int,
     *   topicsPerLesson: int,
     *   sectionsPerCourse: int,
     *   lessonsPerSection: int,
     *   quizzesPerSection: int
     * }
     */
    private static function resolveLifterStructureCounts(array $config): array
    {
        $sectionsPerCourse = max(0, (int) ($config['sections_per_course'] ?? self::DEFAULT_SECTIONS_PER_COURSE));
        $lessonsPerSection = max(0, (int) ($config['lessons_per_section'] ?? self::DEFAULT_LESSONS_PER_SECTION));
        $lessonsPerCourse  = max(0, (int) ($config['lessons_per_course'] ?? 0));

        if ($lessonsPerSection > 0 && $sectionsPerCourse > 0) {
            $lessonsPerCourse = $sectionsPerCourse * $lessonsPerSection;
        }

        return [
            'lessonsPerCourse'  => $lessonsPerCourse,
            'topicsPerLesson'   => 0,
            'sectionsPerCourse' => $sectionsPerCourse,
            'lessonsPerSection' => $lessonsPerSection,
            'quizzesPerSection' => max(0, (int) ($config['quizzes_per_section'] ?? self::DEFAULT_QUIZZES_PER_SECTION)),
        ];
    }

    private static function normalizePassword(mixed $password): ?string
    {
        if (!is_string($password)) {
            return null;
        }

        $password = trim($password);

        return $password !== '' ? $password : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $base = [
            'plugin'             => $this->plugin,
            'courses'            => $this->courses,
            'questions_per_quiz' => $this->questionsPerQuiz,
            'users'              => $this->users,
            'groups'             => $this->groups,
            'user_password'      => $this->userPassword,
        ];

        return match ($this->plugin) {
            'learndash' => array_merge($base, [
                'lessons_per_course' => $this->lessonsPerCourse,
                'topics_per_lesson'  => $this->topicsPerLesson,
                'quizzes_per_lesson' => $this->quizzesPerSection,
            ]),
            'lifterlms' => array_merge($base, [
                'sections_per_course' => $this->sectionsPerCourse,
                'lessons_per_section' => $this->lessonsPerSection,
                'lessons_per_course'  => $this->lessonsPerCourse,
                'quizzes_per_section' => $this->quizzesPerSection,
            ]),
            'tangible-lms' => array_merge($base, [
                'modules_per_course'  => $this->modulesPerCourse,
                'lessons_per_course'  => $this->lessonsPerCourse,
                'quizzes_per_section' => $this->quizzesPerSection,
            ]),
            default => array_merge($base, [
                'lessons_per_course'  => $this->lessonsPerCourse,
                'quizzes_per_section' => $this->quizzesPerSection,
            ]),
        };
    }
}
