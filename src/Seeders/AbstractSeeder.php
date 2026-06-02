<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeders;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Seeding\SeedQueueItem;
use Tangible\Populater\Support\DummyContent;
use Tangible\Populater\Support\GroupIndexResolver;

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
    public const USER_META_MARKER = '_tangible_populater_user';

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
    protected function getPostType(string $entity): string
    {
        return $this->plugin->getEntitySchema()->getPostType($entity);
    }

    /**
     * Meta to set after a post is created.
     *
     * @param array<string, mixed> $context  e.g. courseId, lessonId, index
     * @return array<string, mixed>
     */
    protected function getMetaFor(string $entity, array $context): array
    {
        return $this->plugin->getEntitySchema()->resolveMeta($entity, $context);
    }

    /**
     * Title prefix for generated content.
     */
    protected function getTitlePrefix(string $entity): string
    {
        return $this->plugin->getEntitySchema()->getTitlePrefix($entity, $this->getName());
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
                $this->maybeAssignCourseToGroup($postId, $options);
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
    public function seedQuizzes(int $count, int $parentId, array $options = []): array
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
                $this->applyMeta($postId, $this->getMetaFor('quizzes', ['parentId' => $parentId]));
                $this->afterQuizCreated($postId, $parentId, $index, $options);
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
        if (array_key_exists('index', $options)) {
            return $this->createSeededUserAtIndex((int) $options['index'], $options);
        }

        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $ids = array_merge($ids, $this->createSeededUserAtIndex($i, $options));
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedGroups(int $count, array $options = []): array
    {
        if (!$this->hasGroupSupport()) {
            return [];
        }

        $ids = [];
        $index = (int) ($options['index'] ?? 1);
        $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('groups'), $index);
        $postId = $this->insertPost([
            'post_title'   => $title,
            'post_type'    => $this->getPostType('groups'),
            'post_status'  => 'publish',
            'post_content' => DummyContent::group($title, $index),
            'post_excerpt' => DummyContent::excerpt('group', $title, $index),
        ]);

        if ($postId > 0) {
            $this->afterGroupCreated($postId, $index, $options);
            $ids[] = $postId;
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedGroupAdmins(int $count, array $options = []): array
    {
        $index = (int) ($options['index'] ?? 1);

        $userId = $this->createWpUser([
            'role_type' => 'groupadmin',
            'index'     => $index,
            'password'  => $options['user_password'] ?? null,
        ]);

        if ($userId <= 0) {
            return [];
        }

        $this->afterGroupAdminCreated($userId, $index, $options);
        $this->maybeAssignUserToGroup($userId, $options, true);

        return [$userId];
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
        $types = ['courses', 'lessons', 'quizzes', 'users', 'certificates'];

        if ($this->hasGroupSupport()) {
            $types[] = 'groups';
        }

        $types[] = 'group_admins';

        return $types;
    }

    /**
     * @param array<string, mixed>|SeedConfig $config
     * @return list<SeedQueueItem>
     */
    public function buildSeedQueue(array|SeedConfig $config): array
    {
        $seedConfig = $config instanceof SeedConfig ? $config : SeedConfig::fromArray($config);
        $queue      = [];
        $shared     = $this->sharedQueueData($seedConfig);

        if ($seedConfig->groups > 0) {
            if ($this->hasGroupSupport()) {
                for ($g = 1; $g <= $seedConfig->groups; $g++) {
                    $queue[] = new SeedQueueItem('group', array_merge($shared, ['index' => $g]));
                }
            }

            for ($g = 1; $g <= $seedConfig->groups; $g++) {
                $queue[] = new SeedQueueItem('group_admin', array_merge($shared, ['index' => $g]));
            }
        }

        for ($c = 1; $c <= $seedConfig->courses; $c++) {
            $courseData = array_merge($shared, ['index' => $c]);

            if ($seedConfig->groups > 0) {
                $courseData['group_index'] = GroupIndexResolver::resolve(
                    $c,
                    $seedConfig->courses,
                    $seedConfig->groups,
                );
            }

            $queue[] = new SeedQueueItem('course', $courseData);

            for ($l = 1; $l <= $seedConfig->lessonsPerCourse; $l++) {
                $queue[] = new SeedQueueItem('lesson', [
                    'course_index'        => $c,
                    'index'               => $l,
                    'lessons_per_course'  => $seedConfig->lessonsPerCourse,
                    'topics_per_lesson'   => $seedConfig->topicsPerLesson,
                    'sections_per_course' => $seedConfig->sectionsPerCourse,
                    'modules_per_course'  => $seedConfig->modulesPerCourse,
                    'group_index'         => $courseData['group_index'] ?? 0,
                ]);
            }

            foreach ($this->buildQuizQueueItems($c, $seedConfig) as $item) {
                $queue[] = $item;
            }
        }

        for ($u = 1; $u <= $seedConfig->users; $u++) {
            $userData = array_merge($shared, ['index' => $u]);

            if ($seedConfig->groups > 0) {
                $userData['group_index'] = GroupIndexResolver::resolve(
                    $u,
                    $seedConfig->users,
                    $seedConfig->groups,
                );
            }

            $queue[] = new SeedQueueItem('user', $userData);
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
     * @param array<string, mixed> $options
     * @return list<int>
     */
    protected function createSeededUserAtIndex(int $index, array $options): array
    {
        $userId = $this->createWpUser([
            'role_type' => 'student',
            'index'     => $index,
            'password'  => $options['user_password'] ?? null,
        ]);

        if ($userId <= 0) {
            return [];
        }

        $this->afterUserCreated($userId, $index, $options);
        $this->maybeAssignUserToGroup($userId, $options, false);

        return [$userId];
    }

    /**
     * @param array<string, mixed> $options  Keys: role_type, index, password
     */
    protected function createWpUser(array $options): int
    {
        $index    = (int) ($options['index'] ?? 1);
        $roleType = (string) ($options['role_type'] ?? 'student');
        $username = $roleType . $index;
        $email    = $username . '@example.com';
        $password = is_string($options['password'] ?? null) && $options['password'] !== ''
            ? (string) $options['password']
            : wp_generate_password();

        if (function_exists('username_exists') && username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false, false);
        }

        $userId = wp_create_user($username, $password, $email);

        if (is_wp_error($userId) || !is_int($userId)) {
            return 0;
        }

        if (function_exists('update_user_meta')) {
            update_user_meta($userId, self::USER_META_MARKER, 1);
        }

        return $userId;
    }

    protected function hasGroupSupport(): bool
    {
        $schema = $this->plugin->getEntitySchema();

        if (!isset($schema->postTypes['groups'])) {
            return false;
        }

        if (!function_exists('post_type_exists')) {
            return true;
        }

        return post_type_exists($schema->getPostType('groups'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedQueueData(SeedConfig $seedConfig): array
    {
        $data = [
            'groups'       => $seedConfig->groups,
            'total_courses' => $seedConfig->courses,
            'total_users'  => $seedConfig->users,
        ];

        if ($seedConfig->userPassword !== null) {
            $data['user_password'] = $seedConfig->userPassword;
        }

        return $data;
    }

    /** @param array<string, mixed> $options */
    protected function maybeAssignCourseToGroup(int $courseId, array $options): void
    {
        $groupId    = (int) ($options['group_id'] ?? 0);
        $groupIndex = (int) ($options['group_index'] ?? 0);

        if ($groupId <= 0 && $groupIndex <= 0) {
            return;
        }

        $this->assignCourseToGroup($courseId, $groupId, $groupIndex, $options);
    }

    /** @param array<string, mixed> $options */
    protected function maybeAssignUserToGroup(int $userId, array $options, bool $isGroupAdmin): void
    {
        $groupId    = (int) ($options['group_id'] ?? 0);
        $groupIndex = (int) ($options['group_index'] ?? 0);

        if ($groupId <= 0 && $groupIndex <= 0) {
            return;
        }

        $this->assignUserToGroup($userId, $groupId, $groupIndex, $isGroupAdmin, $options);
    }

    /** @param array<string, mixed> $options */
    protected function assignCourseToGroup(
        int $courseId,
        int $groupId,
        int $groupIndex,
        array $options = [],
    ): void {}

    /** @param array<string, mixed> $options */
    protected function assignUserToGroup(
        int $userId,
        int $groupId,
        int $groupIndex,
        bool $isGroupAdmin,
        array $options = [],
    ): void {}

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
    protected function afterUserCreated(int $userId, int $index, array $options = []): void {}

    /** @param array<string, mixed> $options */
    protected function afterGroupCreated(int $postId, int $index, array $options = []): void {}

    /** @param array<string, mixed> $options */
    protected function afterGroupAdminCreated(int $userId, int $index, array $options = []): void {}

    /** @param array<string, mixed> $options */
    protected function afterLessonCreated(int $postId, int $courseId, int $index, array $options = []): void {}

    /** @param array<string, mixed> $options */
    protected function afterQuizCreated(int $postId, int $parentId, int $index, array $options = []): void {}

    /**
     * @return list<SeedQueueItem>
     */
    protected function buildQuizQueueItems(int $courseIndex, SeedConfig $seedConfig): array
    {
        $quizParent = $this->plugin->getEntitySchema()->quizParentEntity;
        $items      = [];
        $base       = [
            'course_index'       => $courseIndex,
            'questions_per_quiz' => $seedConfig->questionsPerQuiz,
            'quiz_parent_entity' => $quizParent,
        ];

        if ($quizParent === 'topics') {
            for ($l = 1; $l <= $seedConfig->lessonsPerCourse; $l++) {
                for ($t = 1; $t <= $seedConfig->topicsPerLesson; $t++) {
                    for ($q = 1; $q <= $seedConfig->quizzesPerSection; $q++) {
                        $items[] = new SeedQueueItem('quiz', array_merge($base, [
                            'lesson_index'      => $l,
                            'quiz_parent_index' => $t,
                            'index'             => $q,
                        ]));
                    }
                }
            }

            return $items;
        }

        if ($quizParent === 'sections') {
            for ($s = 1; $s <= $seedConfig->sectionsPerCourse; $s++) {
                for ($q = 1; $q <= $seedConfig->quizzesPerSection; $q++) {
                    $items[] = new SeedQueueItem('quiz', array_merge($base, [
                        'quiz_parent_index' => $s,
                        'index'             => $q,
                    ]));
                }
            }

            return $items;
        }

        if ($quizParent === 'modules') {
            for ($m = 1; $m <= $seedConfig->modulesPerCourse; $m++) {
                for ($q = 1; $q <= $seedConfig->quizzesPerSection; $q++) {
                    $items[] = new SeedQueueItem('quiz', array_merge($base, [
                        'quiz_parent_index' => $m,
                        'index'             => $q,
                    ]));
                }
            }

            return $items;
        }

        for ($l = 1; $l <= $seedConfig->lessonsPerCourse; $l++) {
            for ($q = 1; $q <= $seedConfig->quizzesPerSection; $q++) {
                $items[] = new SeedQueueItem('quiz', array_merge($base, [
                    'lesson_index' => $l,
                    'index'        => $q,
                ]));
            }
        }

        return $items;
    }

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
