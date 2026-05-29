<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Seeding\SeedQueueItem;
use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Tests\Support\TestLmsPlugin;
use Brain\Monkey\Functions;

class AbstractSeederTest extends \WPTestCase
{
    /** @var AbstractSeeder&\PHPUnit\Framework\MockObject\MockObject */
    private AbstractSeeder $seeder;

    protected function setUp(): void
    {
        parent::setUp();

        $plugin = new TestLmsPlugin();

        $this->seeder = $this->getMockBuilder(AbstractSeeder::class)
            ->setConstructorArgs([$plugin])
            ->getMockForAbstractClass();
    }

    public function test_identity_from_plugin(): void
    {
        $this->assertSame('Test LMS', $this->seeder->getName());
        $this->assertSame('test-lms', $this->seeder->getSlug());
    }

    public function test_get_post_type_reads_from_plugin_schema(): void
    {
        $reflection = new \ReflectionClass(AbstractSeeder::class);
        $method     = $reflection->getMethod('getPostType');
        $method->setAccessible(true);

        $this->assertSame('test_course', $method->invoke($this->seeder, 'courses'));
        $this->assertSame('test_lesson', $method->invoke($this->seeder, 'lessons'));
    }

    public function test_get_seedable_types_returns_default_set(): void
    {
        $types = $this->seeder->getSeedableTypes();

        $this->assertContains('courses', $types);
        $this->assertContains('lessons', $types);
        $this->assertContains('quizzes', $types);
        $this->assertContains('users', $types);
    }

    public function test_build_seed_queue_returns_seed_queue_items(): void
    {
        $config = ['plugin' => 'test-lms', 'courses' => 2, 'lessons_per_course' => 3, 'users' => 5];

        $queue = $this->seeder->buildSeedQueue($config);

        $this->assertNotEmpty($queue);
        $this->assertContainsOnlyInstancesOf(SeedQueueItem::class, $queue);
    }

    public function test_build_seed_queue_accepts_seed_config(): void
    {
        $config = new SeedConfig('test-lms', 3, 0, 0, 0, 0, 0, 0, 4);
        $queue  = $this->seeder->buildSeedQueue($config);

        $courseItems = array_filter($queue, static fn(SeedQueueItem $item) => $item->type === 'course');
        $userItems   = array_filter($queue, static fn(SeedQueueItem $item) => $item->type === 'user');

        $this->assertCount(3, $courseItems);
        $this->assertCount(4, $userItems);
    }

    public function test_seed_courses_uses_post_type_from_get_post_type(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(fn($args) => ($args['post_type'] ?? '') === 'test_course'))
            ->andReturn(1);
        Functions\when('is_wp_error')->justReturn(false);

        $ids = $this->seeder->seedCourses(1);

        $this->assertSame([1], $ids);
    }

    public function test_seed_courses_includes_dummy_content_and_excerpt(): void
    {
        Functions\expect('wp_insert_post')
            ->once()
            ->with(\Mockery::on(function (array $args): bool {
                $this->assertSame('test_course', $args['post_type'] ?? '');
                $this->assertStringContainsString('<h2>', (string) ($args['post_content'] ?? ''));
                $this->assertStringContainsString('Tangible Populator', (string) ($args['post_excerpt'] ?? ''));

                return true;
            }))
            ->andReturn(1);
        Functions\when('is_wp_error')->justReturn(false);

        $this->seeder->seedCourses(1);
    }
}
