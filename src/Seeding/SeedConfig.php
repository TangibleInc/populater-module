<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Validated seeding configuration passed to queue builders and processes.
 */
final class SeedConfig
{
    public function __construct(
        public readonly string $plugin,
        public readonly int $courses,
        public readonly int $lessonsPerCourse,
        public readonly int $quizzesPerLesson,
        public readonly int $users,
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

        return new self(
            plugin: $plugin,
            courses: max(0, (int) ($config['courses'] ?? 0)),
            lessonsPerCourse: max(0, (int) ($config['lessons_per_course'] ?? 0)),
            quizzesPerLesson: max(0, (int) ($config['quizzes_per_lesson'] ?? 0)),
            users: max(0, (int) ($config['users'] ?? 0)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plugin'             => $this->plugin,
            'courses'            => $this->courses,
            'lessons_per_course' => $this->lessonsPerCourse,
            'quizzes_per_lesson' => $this->quizzesPerLesson,
            'users'              => $this->users,
        ];
    }
}
