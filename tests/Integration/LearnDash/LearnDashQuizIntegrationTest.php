<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Integration\LearnDash;

use Tangible\Populater\Registry\LmsPluginRegistry;
use Tangible\Populater\Tests\Integration\WordPressIntegrationTestCase;
use Tangible\Populater\Tests\Support\LmsContentInspector;

/**
 * Focused LearnDash quiz integration coverage for ProQuiz + course step linkage.
 *
 * @group integration
 * @group learndash
 */
class LearnDashQuizIntegrationTest extends WordPressIntegrationTestCase
{
    public function test_seeded_quiz_has_proquiz_and_course_navigation(): void
    {
        if (!$this->isLearnDashActive()) {
            $this->markTestSkipped('LearnDash is not active in this environment.');
        }

        $registry = new LmsPluginRegistry();
        $plugin   = $registry->get('learndash');
        $seeder   = $plugin->createSeeder();
        $maxPostId = $this->maxPostId();

        $courseIds = $seeder->seedCourses(1);
        $this->assertCount(1, $courseIds);

        $lessonIds = $seeder->seedLessons(1, $courseIds[0], [
            'index'             => 1,
            'topics_per_lesson' => 0,
        ]);
        $this->assertCount(1, $lessonIds);

        $quizIds = $seeder->seedQuizzes(1, $lessonIds[0], [
            'course_id'          => $courseIds[0],
            'questions_per_quiz' => 2,
        ]);
        $this->assertCount(1, $quizIds);

        LmsContentInspector::assertSeededStructure(
            $this,
            'learndash',
            courses: 1,
            lessonsPerCourse: 1,
            quizzesPerLesson: 1,
            afterPostId: $maxPostId,
            questionsPerQuiz: 2,
            topicsPerLesson: 0,
        );
    }

    private function isLearnDashActive(): bool
    {
        $registry = new LmsPluginRegistry();

        return $registry->get('learndash')->isActive();
    }

    private function maxPostId(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
    }
}
