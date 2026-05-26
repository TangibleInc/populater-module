<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeders;

use Tangible\Populater\Seeders\AbstractSeeder;
use Brain\Monkey\Functions;

class AbstractSeederTest extends \WPTestCase
{
    /** @var AbstractSeeder&\PHPUnit\Framework\MockObject\MockObject */
    private AbstractSeeder $seeder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeder = $this->getMockForAbstractClass(AbstractSeeder::class);
        $this->seeder->method('getName')->willReturn('Test LMS');
        $this->seeder->method('getSlug')->willReturn('test-lms');
        $this->seeder->method('isActive')->willReturn(true);
    }

    public function test_get_seedable_types_returns_default_set(): void
    {
        $types = $this->seeder->getSeedableTypes();

        $this->assertContains('courses', $types);
        $this->assertContains('lessons', $types);
        $this->assertContains('quizzes', $types);
        $this->assertContains('users', $types);
    }

    public function test_seed_courses_abstract_method_must_be_implemented(): void
    {
        $reflection = new \ReflectionClass(AbstractSeeder::class);
        $method = $reflection->getMethod('seedCourses');

        $this->assertTrue($method->isAbstract());
    }

    public function test_seed_lessons_abstract_method_must_be_implemented(): void
    {
        $reflection = new \ReflectionClass(AbstractSeeder::class);
        $method = $reflection->getMethod('seedLessons');

        $this->assertTrue($method->isAbstract());
    }

    public function test_seed_quizzes_abstract_method_must_be_implemented(): void
    {
        $reflection = new \ReflectionClass(AbstractSeeder::class);
        $method = $reflection->getMethod('seedQuizzes');

        $this->assertTrue($method->isAbstract());
    }

    public function test_seed_users_abstract_method_must_be_implemented(): void
    {
        $reflection = new \ReflectionClass(AbstractSeeder::class);
        $method = $reflection->getMethod('seedUsers');

        $this->assertTrue($method->isAbstract());
    }

    public function test_get_name_abstract_method_must_be_implemented(): void
    {
        $reflection = new \ReflectionClass(AbstractSeeder::class);
        $method = $reflection->getMethod('getName');

        $this->assertTrue($method->isAbstract());
    }

    public function test_get_slug_abstract_method_must_be_implemented(): void
    {
        $reflection = new \ReflectionClass(AbstractSeeder::class);
        $method = $reflection->getMethod('getSlug');

        $this->assertTrue($method->isAbstract());
    }

    public function test_is_active_abstract_method_must_be_implemented(): void
    {
        $reflection = new \ReflectionClass(AbstractSeeder::class);
        $method = $reflection->getMethod('isActive');

        $this->assertTrue($method->isAbstract());
    }

    public function test_build_seed_queue_returns_array_of_items(): void
    {
        $config = ['courses' => 2, 'lessons_per_course' => 3, 'users' => 5];

        $queue = $this->seeder->buildSeedQueue($config);

        $this->assertIsArray($queue);
        $this->assertNotEmpty($queue);
        foreach ($queue as $item) {
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('data', $item);
        }
    }

    public function test_build_seed_queue_respects_courses_count(): void
    {
        $config = ['courses' => 3, 'lessons_per_course' => 0, 'users' => 0];

        $queue = $this->seeder->buildSeedQueue($config);

        $courseItems = array_filter($queue, fn($item) => $item['type'] === 'course');
        $this->assertCount(3, $courseItems);
    }

    public function test_build_seed_queue_respects_users_count(): void
    {
        $config = ['courses' => 0, 'lessons_per_course' => 0, 'users' => 4];

        $queue = $this->seeder->buildSeedQueue($config);

        $userItems = array_filter($queue, fn($item) => $item['type'] === 'user');
        $this->assertCount(4, $userItems);
    }
}
