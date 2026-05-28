<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeders;

/**
 * Base contract for all LMS seeders.
 *
 * Each LMS plugin requires its own concrete implementation that knows how to
 * create the right post types, meta, and taxonomy terms.
 */
abstract class AbstractSeeder
{
    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    /** Human-readable plugin name shown in the UI. */
    abstract public function getName(): string;

    /** Machine-readable slug used as array keys / option names. */
    abstract public function getSlug(): string;

    /** Returns true when the plugin is currently active in WordPress. */
    abstract public function isActive(): bool;

    // -------------------------------------------------------------------------
    // Content creation
    // -------------------------------------------------------------------------

    /**
     * Creates $count courses and returns their post IDs.
     *
     * @param  array<string, mixed> $options  Extra configuration (e.g. title prefix).
     * @return list<int>
     */
    abstract public function seedCourses(int $count, array $options = []): array;

    /**
     * Creates $count lessons attached to $courseId and returns their post IDs.
     *
     * @param  array<string, mixed> $options
     * @return list<int>
     */
    abstract public function seedLessons(int $count, int $courseId, array $options = []): array;

    /**
     * Creates $count quizzes attached to $lessonId and returns their post IDs.
     *
     * @param  array<string, mixed> $options
     * @return list<int>
     */
    abstract public function seedQuizzes(int $count, int $lessonId, array $options = []): array;

    /**
     * Creates $count users and returns their user IDs.
     *
     * @param  array<string, mixed> $options
     * @return list<int>
     */
    abstract public function seedUsers(int $count, array $options = []): array;

    /**
     * Creates $count certificates and returns their post IDs.
     *
     * @param  array<string, mixed> $options
     * @return list<int>
     */
    abstract public function seedCertificates(int $count, array $options = []): array;

    // -------------------------------------------------------------------------
    // Queue building (shared logic)
    // -------------------------------------------------------------------------

    /**
     * Returns the content types this seeder can create.
     *
     * @return list<string>
     */
    public function getSeedableTypes(): array
    {
        return ['courses', 'lessons', 'quizzes', 'users', 'certificates'];
    }

    /**
     * Builds a flat queue of seed items derived from $config so the background
     * process can iterate over them one-by-one.
     *
     * Each item: ['type' => string, 'data' => array<string, mixed>]
     *
     * @param  array<string, mixed> $config  Keys: courses, lessons_per_course, quizzes_per_lesson, users.
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function buildSeedQueue(array $config): array
    {
        $queue            = [];
        $courses          = max(0, (int) ($config['courses']           ?? 0));
        $lessonsPerCourse = max(0, (int) ($config['lessons_per_course'] ?? 0));
        $quizzesPerLesson = max(0, (int) ($config['quizzes_per_lesson'] ?? 0));
        $users            = max(0, (int) ($config['users']             ?? 0));

        for ($c = 1; $c <= $courses; $c++) {
            $queue[] = ['type' => 'course', 'data' => ['index' => $c]];

            for ($l = 1; $l <= $lessonsPerCourse; $l++) {
                $queue[] = ['type' => 'lesson', 'data' => ['course_index' => $c, 'index' => $l]];

                for ($q = 1; $q <= $quizzesPerLesson; $q++) {
                    $queue[] = ['type' => 'quiz', 'data' => ['course_index' => $c, 'lesson_index' => $l, 'index' => $q]];
                }
            }
        }

        for ($u = 1; $u <= $users; $u++) {
            $queue[] = ['type' => 'user', 'data' => ['index' => $u]];
        }

        return $queue;
    }
}
