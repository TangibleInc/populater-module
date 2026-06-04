<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\Registry\LmsPluginRegistry;
use Tangible\Populater\Registry\LmsPlugins;
use Tangible\Populater\Seeding\SeedConfig;
use Brain\Monkey\Functions;

/**
 * Cross-component queue tests (registry + seeder + queue) without LMS-specific mocks.
 */
class SeedingQueueTest extends \WPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LmsPlugins::registerBuiltIn();
        Functions\when('post_type_exists')->justReturn(true);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function lmsSlugProvider(): array
    {
        return [
            'learndash'    => ['learndash', 4],
            'lifterlms'    => ['lifterlms', 4],
            'tangible-lms' => ['tangible-lms', 4],
        ];
    }

    /**
     * @dataProvider lmsSlugProvider
     */
    public function test_build_seed_queue_for_one_of_each_entity(string $slug, int $expectedCount): void
    {
        $registry = new LmsPluginRegistry();
        $seeder   = $registry->createSeeder($slug);
        $config   = new SeedConfig($slug, 1, 1, 1, 0, 1, 1, 0, 1, 1);
        $queue    = $seeder->buildSeedQueue($config);

        $this->assertCount($expectedCount, $queue);

        $types = array_map(static fn($item) => $item->type, $queue);
        $this->assertContains('course', $types);
        $this->assertContains('lesson', $types);
        $this->assertContains('quiz', $types);
        $this->assertContains('user', $types);
    }

    public function test_build_seed_queue_passes_structure_settings_to_lesson_and_quiz_items(): void
    {
        $registry = new LmsPluginRegistry();
        $seeder   = $registry->createSeeder('learndash');
        $config   = new SeedConfig('learndash', 1, 2, 1, 4, 3, 2, 0, 1, 5);
        $queue    = $seeder->buildSeedQueue($config);

        $lesson = array_values(array_filter($queue, static fn($item) => $item->type === 'lesson'))[0];
        $quiz   = array_values(array_filter($queue, static fn($item) => $item->type === 'quiz'))[0];
        $quizItems = array_values(array_filter($queue, static fn($item) => $item->type === 'quiz'));

        $this->assertSame(2, $lesson->data['lessons_per_course']);
        $this->assertSame(3, $lesson->data['topics_per_lesson']);
        $this->assertSame(2, $lesson->data['sections_per_course']);
        $this->assertSame(0, $lesson->data['lessons_per_section']);
        $this->assertSame(1, $lesson->data['modules_per_course']);
        $this->assertSame(4, $quiz->data['questions_per_quiz']);
        $this->assertCount(2, $quizItems, 'LearnDash should queue one quiz per lesson when two lessons are configured.');
    }

    public function test_build_seed_queue_learndash_quizzes_scale_with_lessons_not_topics(): void
    {
        $registry = new LmsPluginRegistry();
        $seeder   = $registry->createSeeder('learndash');
        $config   = SeedConfig::fromArray([
            'plugin'             => 'learndash',
            'courses'            => 1,
            'lessons_per_course' => 3,
            'topics_per_lesson'  => 2,
            'quizzes_per_lesson' => 1,
            'users'              => 0,
        ]);
        $quizItems = array_values(array_filter(
            $seeder->buildSeedQueue($config),
            static fn($item) => $item->type === 'quiz',
        ));

        $this->assertCount(3, $quizItems);
        $this->assertSame(3, $config->lessonsPerCourse);
    }

    public function test_build_seed_queue_adds_group_items_when_groups_configured(): void
    {
        $registry = new LmsPluginRegistry();
        $seeder   = $registry->createSeeder('learndash');
        $config   = new SeedConfig('learndash', 4, 1, 0, 0, 1, 1, 0, 1, 6, 2);
        $queue    = $seeder->buildSeedQueue($config);

        $groupItems = array_filter($queue, static fn($item) => $item->type === 'group');
        $adminItems = array_filter($queue, static fn($item) => $item->type === 'group_admin');

        $this->assertCount(2, $groupItems);
        $this->assertCount(2, $adminItems);

        $courseItems = array_values(array_filter($queue, static fn($item) => $item->type === 'course'));
        $this->assertSame(1, $courseItems[0]->data['group_index']);
        $this->assertSame(1, $courseItems[1]->data['group_index']);
        $this->assertSame(2, $courseItems[2]->data['group_index']);
        $this->assertSame(2, $courseItems[3]->data['group_index']);
    }

    public function test_build_seed_queue_adds_lifterlms_group_items_when_groups_configured(): void
    {
        $registry = new LmsPluginRegistry();
        $seeder   = $registry->createSeeder('lifterlms');
        $config   = new SeedConfig('lifterlms', 2, 1, 0, 0, 0, 0, 0, 0, 4, 2);
        $queue    = $seeder->buildSeedQueue($config);

        $groupItems = array_filter($queue, static fn($item) => $item->type === 'group');
        $adminItems = array_filter($queue, static fn($item) => $item->type === 'group_admin');

        $this->assertCount(2, $groupItems);
        $this->assertCount(2, $adminItems);
    }
}
