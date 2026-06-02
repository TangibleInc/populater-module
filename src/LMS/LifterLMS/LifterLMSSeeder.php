<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Seeding\SeedingIdMap;
use Tangible\Populater\Support\DeterministicTitle;
use Tangible\Populater\Support\DummyContent;

/**
 * Seeder for the LifterLMS plugin.
 *
 * Creates section posts per course so lessons attach to valid course structure.
 */
class LifterLMSSeeder extends AbstractSeeder
{
    private const COURSE_BLOCKS = <<<'HTML'


<!-- wp:llms/pricing-table /-->

<!-- wp:llms/course-syllabus /-->
HTML;

    /** @param array<string, mixed> $options */
    protected function afterCourseCreated(int $postId, int $index, array $options = []): void
    {
        LifterLmsEnrollmentSetup::seedCourseEnrollment($postId, $index);
        $this->appendCourseBlocks($postId);
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedLessons(int $count, int $courseId, array $options = []): array
    {
        $schema            = $this->plugin->getEntitySchema();
        $container         = $schema->container;
        $lessonIndex  = DeterministicTitle::resolveIndex($options, 1);
        $courseIndex  = DeterministicTitle::courseIndex($options);
        $lessonsPerCourse  = max(1, (int) ($options['lessons_per_course'] ?? 1));
        $sectionsPerCourse = max(1, (int) ($options['sections_per_course'] ?? 1));
        $sectionIndex      = $this->resolveContainerIndex($lessonIndex, $lessonsPerCourse, $sectionsPerCourse);
        $sectionId         = $this->ensureSectionForCourse($courseId, $sectionIndex, $options);

        $prefix = $options['title_prefix'] ?? $this->getTitlePrefix('lessons');
        $title  = DeterministicTitle::lesson((string) $prefix, $courseIndex, $lessonIndex);
        $postId = $this->insertPost([
            'post_title'   => $title,
            'post_type'    => $this->getPostType('lessons'),
            'post_status'  => 'publish',
            'post_content' => DummyContent::lesson($title, $lessonIndex),
            'post_excerpt' => DummyContent::excerpt('lesson', $title, $lessonIndex),
            'post_parent'  => $sectionId > 0 ? $sectionId : $courseId,
        ]);

        if ($postId <= 0) {
            return [];
        }

        $this->applyMeta($postId, $this->getMetaFor('lessons', ['courseId' => $courseId]));

        if ($sectionId > 0 && $container !== null && $container->lessonParentMetaKey !== '') {
            update_post_meta($postId, $container->lessonParentMetaKey, $sectionId);
        }

        update_post_meta($postId, '_llms_order', $lessonIndex);

        $this->recordLessonForSection($sectionIndex, $postId, $options);

        return [$postId];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function recordLessonForSection(int $sectionIndex, int $lessonId, array $options): void
    {
        $processId = (string) ($options['process_id'] ?? '');

        if ($processId === '') {
            return;
        }

        SeedingIdMap::recordSectionLesson(
            $processId,
            (int) ($options['course_index'] ?? 0),
            $sectionIndex,
            $lessonId,
            new ProcessRepository(),
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedQuizzes(int $count, int $parentId, array $options = []): array
    {
        $sectionId = (int) ($options['quiz_parent_id'] ?? $parentId);
        $lessonId  = (int) ($options['lesson_id'] ?? 0);

        if ($lessonId <= 0 && $sectionId > 0) {
            $lessonId = $this->resolveLessonForSectionQuiz($sectionId, (int) ($options['index'] ?? 1));
        }

        $ids         = [];
        $prefix      = $options['title_prefix'] ?? $this->getTitlePrefix('quizzes');
        $courseIndex = DeterministicTitle::courseIndex($options);

        for ($i = 1; $i <= $count; $i++) {
            $index  = DeterministicTitle::resolveIndex($options, $i);
            $title  = DeterministicTitle::quiz((string) $prefix, $courseIndex, $options);
            $postId = $this->insertPost([
                'post_title'   => $title,
                'post_type'    => $this->getPostType('quizzes'),
                'post_status'  => 'publish',
                'post_content' => DummyContent::quiz($title, $index),
            ]);

            if ($postId > 0) {
                $this->applyMeta($postId, $this->getMetaFor('quizzes', ['lessonId' => $lessonId]));
                $this->afterQuizCreated($postId, $lessonId, $index, array_merge($options, [
                    'lesson_id'      => $lessonId,
                    'quiz_parent_id' => $sectionId,
                ]));
                $this->seedQuestionsForQuiz($postId, $options);
                $ids[] = $postId;
            }
        }

        return $ids;
    }

    protected function afterQuizCreated(int $postId, int $parentId, int $index, array $options = []): void
    {
        $lessonId = (int) ($options['lesson_id'] ?? $parentId);

        if ($lessonId <= 0 || $postId <= 0) {
            return;
        }

        if ((int) get_post_meta($lessonId, '_llms_quiz', true) > 0) {
            return;
        }

        update_post_meta($lessonId, '_llms_quiz', $postId);
        update_post_meta($lessonId, '_llms_quiz_enabled', 'yes');
    }

    protected function afterQuestionCreated(int $postId, int $quizId, int $index, array $options = []): void
    {
        update_post_meta($postId, '_llms_question_type', 'true_false');
        update_post_meta($postId, '_llms_points', 1);
        update_post_meta($postId, LifterLmsTrueFalseAnswers::CORRECT_MARKER_META, LifterLmsTrueFalseAnswers::correctMarker($index));
        $this->attachTrueFalseChoices($postId, $index);
        $this->appendStressHintToQuestion($postId, $index);

        if ($index > 0) {
            wp_update_post([
                'ID'         => $postId,
                'menu_order' => $index,
            ]);
        }
    }

    private function appendStressHintToQuestion(int $questionId, int $index): void
    {
        $post = get_post($questionId);

        if ($post === null || $post->post_type !== 'llms_question') {
            return;
        }

        $hint = LifterLmsTrueFalseAnswers::stressHint($index);

        if (str_contains((string) $post->post_content, 'populater-stress-hint')) {
            return;
        }

        wp_update_post([
            'ID'           => $questionId,
            'post_content' => rtrim((string) $post->post_content) . "\n" . $hint,
        ]);
    }

    private function attachTrueFalseChoices(int $questionId, int $questionIndex): void
    {
        if ($questionId <= 0) {
            return;
        }

        $choices = LifterLmsTrueFalseAnswers::choices($questionIndex);

        if (function_exists('llms_get_post')) {
            $question = llms_get_post($questionId);

            if ($question instanceof \LLMS_Question && count($question->get_choices()) === 0) {
                foreach (['true', 'false'] as $key) {
                    $choice = $choices[$key];
                    $question->create_choice([
                        'choice'      => $choice['choice'],
                        'correct'     => $choice['correct'],
                        'choice_type' => 'text',
                        'marker'      => $choice['marker'],
                    ]);
                }

                return;
            }
        }

        foreach (['true' => 'a', 'false' => 'b'] as $key => $choiceId) {
            $choice = $choices[$key];
            $this->storeTrueFalseChoiceMeta($questionId, $choiceId, [
                'id'          => $choiceId,
                'choice'      => $choice['choice'],
                'choice_type' => 'text',
                'correct'     => $choice['correct'],
                'marker'      => $choice['marker'],
                'question_id' => $questionId,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function storeTrueFalseChoiceMeta(int $questionId, string $choiceId, array $data): void
    {
        update_post_meta($questionId, '_llms_choice_' . $choiceId, $data);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function ensureSectionForCourse(int $courseId, int $sectionIndex, array $options): int
    {
        $schema    = $this->plugin->getEntitySchema();
        $container = $schema->container;

        if ($container === null) {
            return 0;
        }

        $existing = (int) ($options['section_id'] ?? 0);

        if ($existing > 0) {
            return $existing;
        }

        $cached = get_post_meta($courseId, $container->cacheMetaKey, true);

        if (is_array($cached) && isset($cached[$sectionIndex])) {
            $sectionId = (int) $cached[$sectionIndex];
            $this->recordSectionInIdMap($sectionId, $sectionIndex, $options);

            return $sectionId;
        }

        $courseIndex = DeterministicTitle::courseIndex($options);
        $prefix      = $this->getTitlePrefix($container->entity);
        $title       = DeterministicTitle::section($prefix, $courseIndex, $sectionIndex);
        $sectionId   = $this->insertPost([
            'post_title'   => $title,
            'post_type'    => $schema->getPostType($container->entity),
            'post_status'  => 'publish',
            'post_content' => DummyContent::section($title, $sectionIndex),
            'post_excerpt' => DummyContent::excerpt('section', $title, $sectionIndex),
            'post_parent'  => $courseId,
        ]);

        if ($sectionId > 0) {
            update_post_meta($sectionId, $container->parentMetaKey, $courseId);
            update_post_meta($sectionId, '_llms_order', $sectionIndex);

            if (!is_array($cached)) {
                $cached = [];
            }

            $cached[$sectionIndex] = $sectionId;
            update_post_meta($courseId, $container->cacheMetaKey, $cached);

            $this->recordSectionInIdMap($sectionId, $sectionIndex, $options);
        }

        return $sectionId;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function recordSectionInIdMap(int $sectionId, int $sectionIndex, array $options): void
    {
        $processId = (string) ($options['process_id'] ?? '');

        if ($processId === '') {
            return;
        }

        SeedingIdMap::recordQuizParent(
            $processId,
            'sections',
            (int) ($options['course_index'] ?? 0),
            $sectionIndex,
            $sectionId,
            new ProcessRepository(),
        );
    }

    private function resolveLessonForSectionQuiz(int $sectionId, int $quizIndex): int
    {
        if ($sectionId <= 0) {
            return 0;
        }

        $lessons = get_posts([
            'post_type'      => $this->getPostType('lessons'),
            'post_status'    => 'any',
            'posts_per_page' => 50,
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_key'       => '_llms_order',
            'meta_query'     => [
                [
                    'key'   => '_llms_parent_section',
                    'value' => $sectionId,
                ],
            ],
        ]);

        if ($lessons === []) {
            return 0;
        }

        $position = count($lessons) - max(1, $quizIndex);

        return (int) ($lessons[max(0, $position)]->ID ?? $lessons[array_key_last($lessons)]->ID);
    }

    private function appendCourseBlocks(int $courseId): void
    {
        $post = get_post($courseId);

        if ($post === null || $post->post_type !== 'course') {
            return;
        }

        wp_update_post([
            'ID'           => $courseId,
            'post_content' => rtrim((string) $post->post_content) . self::COURSE_BLOCKS,
        ]);
    }

    private function resolveContainerIndex(int $itemIndex, int $itemsPerContainer, int $containers): int
    {
        $containers = max(1, min($containers, $itemsPerContainer));

        return (int) min($containers, max(1, (int) ceil($itemIndex * $containers / $itemsPerContainer)));
    }

    protected function hasGroupSupport(): bool
    {
        if (function_exists('llms_create_group')) {
            return true;
        }

        return function_exists('post_type_exists') && post_type_exists('llms_group');
    }

    /**
     * @param array<string, mixed> $options
     * @return list<int>
     */
    public function seedGroups(int $count, array $options = []): array
    {
        if (!$this->hasGroupSupport() || !class_exists(\LLMS_Group::class)) {
            return parent::seedGroups($count, $options);
        }

        $index  = DeterministicTitle::resolveIndex($options, 1);
        $prefix = $options['title_prefix'] ?? $this->getTitlePrefix('groups');
        $title  = DeterministicTitle::group((string) $prefix, $index);
        $seats  = $this->resolveGroupSeatCount($options);
        $meta  = ['_llms_seats' => $seats];

        if (function_exists('llms_groups')) {
            $integration = llms_groups()->get_integration();
            $meta['_llms_visibility'] = $integration->get_option('visibility', 'private');
        }

        // Create without llms_create_group() so the current WP admin is not enrolled as primary admin.
        $group = new \LLMS_Group('new', [
            'post_title'   => $title,
            'post_status'  => 'publish',
            'post_content' => DummyContent::group($title, $index),
            'post_excerpt' => DummyContent::excerpt('group', $title, $index),
            'meta_input'   => $meta,
        ]);

        $groupId = (int) $group->get('id');

        if ($groupId > 0 && (int) $group->get('seats') !== $seats) {
            $group->set('seats', $seats);
        }

        return $groupId > 0 ? [$groupId] : [];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveGroupSeatCount(array $options): int
    {
        $totalUsers  = max(0, (int) ($options['total_users'] ?? 0));
        $totalGroups = max(1, (int) ($options['groups'] ?? 1));
        $perGroup    = (int) ceil($totalUsers / $totalGroups);

        return max(2, $perGroup + 2);
    }

    /** @param array<string, mixed> $options */
    protected function assignCourseToGroup(
        int $courseId,
        int $groupId,
        int $groupIndex,
        array $options = [],
    ): void {
        if ($courseId <= 0 || $groupId <= 0) {
            return;
        }

        $this->trackGroupCourse($groupId, $courseId);

        if (function_exists('get_llms_group')) {
            $group = get_llms_group($groupId);

            if ($group && !$group->get('post_id')) {
                $group->set('post_id', $courseId);
            }
        }

        $this->enrollGroupMembersInCourse($groupId, $courseId);
    }

    /** @param array<string, mixed> $options */
    protected function assignUserToGroup(
        int $userId,
        int $groupId,
        int $groupIndex,
        bool $isGroupAdmin,
        array $options = [],
    ): void {
        if ($userId <= 0 || $groupId <= 0) {
            return;
        }

        $trigger = 'populater_group_' . $groupId;
        $role    = $isGroupAdmin ? 'primary_admin' : 'member';

        if (class_exists(\LLMS_Groups_Enrollment::class)) {
            \LLMS_Groups_Enrollment::add($userId, $groupId, $trigger, $role);
        } elseif (function_exists('llms_enroll_student')) {
            llms_enroll_student($userId, $groupId, $trigger);
        }

        $this->trackGroupMember($groupId, $userId);
        $this->enrollUserInGroupCourses($groupId, $userId);
    }

    private function trackGroupCourse(int $groupId, int $courseId): void
    {
        $courses = get_post_meta($groupId, '_populater_group_courses', true);

        if (!is_array($courses)) {
            $courses = [];
        }

        $courses[] = $courseId;
        update_post_meta($groupId, '_populater_group_courses', array_values(array_unique(array_map('intval', $courses))));
    }

    private function trackGroupMember(int $groupId, int $userId): void
    {
        $members = get_post_meta($groupId, '_populater_group_member_ids', true);

        if (!is_array($members)) {
            $members = [];
        }

        $members[] = $userId;
        update_post_meta($groupId, '_populater_group_member_ids', array_values(array_unique(array_map('intval', $members))));
    }

    private function enrollGroupMembersInCourse(int $groupId, int $courseId): void
    {
        if (!function_exists('llms_enroll_student')) {
            return;
        }

        $members = get_post_meta($groupId, '_populater_group_member_ids', true);

        if (!is_array($members)) {
            return;
        }

        foreach ($members as $memberId) {
            llms_enroll_student((int) $memberId, $courseId, 'populater_group_' . $groupId);
        }
    }

    private function enrollUserInGroupCourses(int $groupId, int $userId): void
    {
        if (!function_exists('llms_enroll_student')) {
            return;
        }

        $courses = get_post_meta($groupId, '_populater_group_courses', true);

        if (!is_array($courses)) {
            return;
        }

        foreach ($courses as $courseId) {
            llms_enroll_student($userId, (int) $courseId, 'populater_group_' . $groupId);
        }
    }

    /** @param array<string, mixed> $options */
    protected function afterUserCreated(int $userId, int $index, array $options = []): void
    {
        LifterLmsStudentProfile::populate($userId, $index, 'student');
    }

    /** @param array<string, mixed> $options */
    protected function afterGroupAdminCreated(int $userId, int $index, array $options = []): void
    {
        LifterLmsStudentProfile::populate($userId, $index, 'groupadmin');
    }
}
