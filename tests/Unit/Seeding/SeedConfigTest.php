<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeding;

use Tangible\Populater\Seeding\SeedConfig;

class SeedConfigTest extends \WPTestCase
{
    public function test_from_array_normalizes_counts(): void
    {
        $config = SeedConfig::fromArray([
            'plugin'             => 'learndash',
            'courses'            => 2,
            'lessons_per_course' => 3,
            'quizzes_per_lesson' => 1,
            'users'              => 4,
        ]);

        $this->assertSame('learndash', $config->plugin);
        $this->assertSame(2, $config->courses);
        $this->assertSame(3, $config->lessonsPerCourse);
        $this->assertSame(1, $config->quizzesPerLesson);
        $this->assertSame(4, $config->users);
    }

    public function test_from_array_requires_plugin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SeedConfig::fromArray(['courses' => 1]);
    }

    public function test_negative_counts_clamped_to_zero(): void
    {
        $config = SeedConfig::fromArray([
            'plugin'  => 'learndash',
            'courses' => -5,
        ]);

        $this->assertSame(0, $config->courses);
    }
}
