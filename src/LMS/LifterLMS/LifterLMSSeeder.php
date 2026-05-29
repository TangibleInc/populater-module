<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Seeding\SeedingIdMap;
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
        $lessonIndex       = (int) ($options['index'] ?? 1);
        $lessonsPerCourse  = max(1, (int) ($options['lessons_per_course'] ?? 1));
        $sectionsPerCourse = max(1, (int) ($options['sections_per_course'] ?? 1));
        $sectionIndex      = $this->resolveContainerIndex($lessonIndex, $lessonsPerCourse, $sectionsPerCourse);
        $sectionId         = $this->ensureSectionForCourse($courseId, $sectionIndex, $options);

        $index = $lessonIndex;
        $title = $this->defaultTitle($options['title_prefix'] ?? $this->getTitlePrefix('lessons'), $index);
        $postId = $this->insertPost([
            'post_title'   => $title,
            'post_type'    => $this->getPostType('lessons'),
            'post_status'  => 'publish',
            'post_content' => DummyContent::lesson($title, $index),
            'post_excerpt' => DummyContent::excerpt('lesson', $title, $index),
            'post_parent'  => $sectionId > 0 ? $sectionId : $courseId,
        ]);

        if ($postId <= 0) {
            return [];
        }

        $this->applyMeta($postId, $this->getMetaFor('lessons', ['courseId' => $courseId]));

        if ($sectionId > 0 && $container !== null && $container->lessonParentMetaKey !== '') {
            update_post_meta($postId, $container->lessonParentMetaKey, $sectionId);
        }

        update_post_meta($postId, '_llms_order', $index);

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

        $title     = $this->defaultTitle($this->getTitlePrefix($container->entity), $sectionIndex);
        $sectionId = $this->insertPost([
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
}
