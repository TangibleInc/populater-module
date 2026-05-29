<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeders;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Seeding\SeedQueueItem;
use Tangible\Populater\Support\DummyContent;

/**
 * Base contract for all LMS seeders.
 *
 * Default {@see seedCourses()}, {@see seedLessons()}, etc. assume WordPress posts
 * and post meta. LMS plugins that use custom tables, wp_options, or vendor APIs
 * should override the relevant methods and keep the shared queue via
 * {@see buildSeedQueue()}.
 *
 * @see docs/LMS-EXTENSION.md
 */
abstract class AbstractSeeder
{
    public function __construct(
        protected readonly AbstractLmsPlugin $plugin,
    ) {}

    public function getName(): string
    {
        return $this->plugin->getName();
    }

    public function getSlug(): string
    {
        return $this->plugin->getSlug();
    }

    public function isActive(): bool
    {
        return $this->plugin->isActive();
    }

    /**
     * Post type for an entity key: courses, lessons, quizzes, certificates.
     */
    abstract protected function getPostType(string $entity): string;

    /**
     * Meta to set after a post is created.
     *
     * @param array<string, mixed> $context  e.g. courseId, lessonId, index
     * @return array<string, mixed>
     */
    protected function getMetaFor(string $entity, array $context): array
    {
        return match ($entity) {
            'lessons'   => ['course_id' => (int) ($context['courseId'] ?? 0)],
            'quizzes'   => ['lesson_id' => (int) ($context['lessonId'] ?? 0)],
            'questions' => ['quiz_id' => (int) ($context['quizId'] ?? 0)],
            default     => [],
        };
    }

    /**
     * Title prefix for generated content.
     */
    protected function getTitlePrefix(string $entity): string
    {
        return $this->getName() . ' ' . ucfirst(rtrim($entity, 's'));
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedCourses(int $count, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('courses'), $i);
            $postId = $this->insertPost([
                'post_title'   => $title,
                'post_type'    => $this->getPostType('courses'),
                'post_status'  => 'publish',
                'post_content' => DummyContent::course($title, $i),
                'post_excerpt' => DummyContent::excerpt('course', $title, $i),
            ]);

            if ($postId > 0) {
                $this->afterCourseCreated($postId, $i, $options);
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedLessons(int $count, int $courseId, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $index = (int) ($options['index'] ?? $i);
            $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('lessons'), $index);
            $postId = $this->insertPost([
                'post_title'   => $title,
                'post_type'    => $this->getPostType('lessons'),
                'post_status'  => 'publish',
                'post_content' => DummyContent::lesson($title, $index),
                'post_excerpt' => DummyContent::excerpt('lesson', $title, $index),
                'post_parent'  => $courseId,
            ]);

            if ($postId > 0) {
                $this->applyMeta($postId, $this->getMetaFor('lessons', ['courseId' => $courseId]));
                $this->afterLessonCreated($postId, $courseId, $index, $options);
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedQuizzes(int $count, int $lessonId, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $index = (int) ($options['index'] ?? $i);
            $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('quizzes'), $index);
            $postId = $this->insertPost([
                'post_title'   => $title,
                'post_type'    => $this->getPostType('quizzes'),
                'post_status'  => 'publish',
                'post_content' => DummyContent::quiz($title, $index),
            ]);

            if ($postId > 0) {
                $this->applyMeta($postId, $this->getMetaFor('quizzes', ['lessonId' => $lessonId]));
                $this->afterQuizCreated($postId, $lessonId, $index, $options);
                $this->seedQuestionsForQuiz($postId, $options);
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedQuestions(int $count, int $quizId, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('questions'), $i);
            $postId = $this->insertPost([
                'post_title'   => $title,
                'post_type'    => $this->getPostType('questions'),
                'post_status'  => 'publish',
                'post_content' => DummyContent::question($title, $i),
            ]);

            if ($postId > 0) {
                $this->applyMeta($postId, $this->getMetaFor('questions', ['quizId' => $quizId]));
                $this->afterQuestionCreated($postId, $quizId, $i, $options);
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedUsers(int $count, array $options = []): array
    {
        $ids = [];
        $prefix = (string) ($options['user_prefix'] ?? strtolower(str_replace('-', '_', $this->getSlug())) . '_user');

        for ($i = 1; $i <= $count; $i++) {
            $userId = $this->createWpUser([
                'prefix' => $prefix,
                'index'  => $i,
            ]);

            if ($userId > 0) {
                $ids[] = $userId;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedCertificates(int $count, array $options = []): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $postId = $this->insertPost([
                'post_title'   => $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('certificates'), $i),
                'post_type'    => $this->getPostType('certificates'),
                'post_status'  => 'publish',
                'post_content' => sprintf('Sample certificate %d.', $i),
            ]);

            if ($postId > 0) {
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    public function getSeedableTypes(): array
    {
        return ['courses', 'lessons', 'quizzes', 'users', 'certificates'];
    }

    /**
     * @param array<string, mixed>|SeedConfig $config
     * @return list<SeedQueueItem>
     */
    public function buildSeedQueue(array|SeedConfig $config): array
    {
        $seedConfig = $config instanceof SeedConfig ? $config : SeedConfig::fromArray($config);
        $queue      = [];

        for ($c = 1; $c <= $seedConfig->courses; $c++) {
            $queue[] = new SeedQueueItem('course', ['index' => $c]);

            for ($l = 1; $l <= $seedConfig->lessonsPerCourse; $l++) {
                $queue[] = new SeedQueueItem('lesson', [
                    'course_index'        => $c,
                    'index'               => $l,
                    'lessons_per_course'  => $seedConfig->lessonsPerCourse,
                    'topics_per_lesson'   => $seedConfig->topicsPerLesson,
                    'sections_per_course' => $seedConfig->sectionsPerCourse,
                    'modules_per_course'  => $seedConfig->modulesPerCourse,
                ]);

                for ($q = 1; $q <= $seedConfig->quizzesPerLesson; $q++) {
                    $queue[] = new SeedQueueItem('quiz', [
                        'course_index'       => $c,
                        'lesson_index'       => $l,
                        'index'              => $q,
                        'questions_per_quiz' => $seedConfig->questionsPerQuiz,
                    ]);
                }
            }
        }

        for ($u = 1; $u <= $seedConfig->users; $u++) {
            $queue[] = new SeedQueueItem('user', ['index' => $u]);
        }

        return $queue;
    }

    // -------------------------------------------------------------------------
    // Protected helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $args
     */
    protected function insertPost(array $args): int
    {
        $postId = wp_insert_post($args);

        if (is_wp_error($postId) || !is_int($postId)) {
            return 0;
        }

        return $postId;
    }

    /**
     * @param array<string, mixed> $options  Keys: prefix, index
     */
    protected function createWpUser(array $options): int
    {
        $index    = (int) ($options['index'] ?? 1);
        $prefix   = (string) ($options['prefix'] ?? 'populater_user');
        $unique   = uniqid((string) $index, true);
        $username = $prefix . '_' . $unique;
        $email    = $username . '@example.com';
        $password = wp_generate_password();
        $userId   = wp_create_user($username, $password, $email);

        if (is_wp_error($userId) || !is_int($userId)) {
            return 0;
        }

        return $userId;
    }

    protected function defaultTitle(string $prefix, int $index): string
    {
        return "$prefix $index";
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function applyMeta(int $postId, array $meta): void
    {
        foreach ($meta as $key => $value) {
            if ($value !== 0 && $value !== '') {
                update_post_meta($postId, (string) $key, $value);
            }
        }
    }

    /** @param array<string, mixed> $options */
    protected function afterCourseCreated(int $postId, int $index, array $options = []): void {}

    /** @param array<string, mixed> $options */
    protected function afterLessonCreated(int $postId, int $courseId, int $index, array $options = []): void {}

    /** @param array<string, mixed> $options */
    protected function afterQuizCreated(int $postId, int $lessonId, int $index, array $options = []): void {}

    /** @param array<string, mixed> $options */
    protected function afterQuestionCreated(int $postId, int $quizId, int $index, array $options = []): void {}

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    protected function seedQuestionsForQuiz(int $quizId, array $options = []): array
    {
        $count = (int) ($options['questions_per_quiz'] ?? 0);

        if ($count <= 0 || $quizId <= 0) {
            return [];
        }

        return $this->seedQuestions($count, $quizId, $options);
    }
}
