<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Seeders\AbstractSeeder;
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

        return [$postId];
    }

    protected function afterQuizCreated(int $postId, int $lessonId, int $index, array $options = []): void
    {
        if ($lessonId <= 0 || $postId <= 0) {
            return;
        }

        // LifterLMS exposes quizzes on lessons via lesson meta (one quiz per lesson).
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
            return (int) $cached[$sectionIndex];
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
        }

        return $sectionId;
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
