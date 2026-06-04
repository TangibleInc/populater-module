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
            'quizzes_per_section' => 1,
            'questions_per_quiz' => 5,
            'users'              => 4,
        ]);

        $this->assertSame('learndash', $config->plugin);
        $this->assertSame(2, $config->courses);
        $this->assertSame(3, $config->lessonsPerCourse);
        $this->assertSame(1, $config->quizzesPerSection);
        $this->assertSame(5, $config->questionsPerQuiz);
        $this->assertSame(10, $config->topicsPerLesson);
        $this->assertSame(0, $config->sectionsPerCourse);
        $this->assertSame(1, $config->modulesPerCourse);
        $this->assertSame(4, $config->users);
        $this->assertSame(0, $config->groups);
        $this->assertNull($config->userPassword);
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

    public function test_questions_per_quiz_defaults_to_ten(): void
    {
        $config = SeedConfig::fromArray([
            'plugin' => 'learndash',
        ]);

        $this->assertSame(10, $config->questionsPerQuiz);
    }

    public function test_topics_per_lesson_defaults_to_ten(): void
    {
        $config = SeedConfig::fromArray([
            'plugin' => 'learndash',
        ]);

        $this->assertSame(10, $config->topicsPerLesson);
    }

    public function test_sections_and_modules_use_defaults(): void
    {
        $config = SeedConfig::fromArray([
            'plugin' => 'lifterlms',
        ]);

        $this->assertSame(10, $config->sectionsPerCourse);
        $this->assertSame(1, $config->modulesPerCourse);
    }

    public function test_to_array_includes_learndash_structure_settings(): void
    {
        $config = SeedConfig::fromArray([
            'plugin'             => 'learndash',
            'lessons_per_course' => 10,
            'topics_per_lesson'  => 4,
            'quizzes_per_lesson' => 2,
        ]);

        $array = $config->toArray();
        $this->assertSame(10, $array['lessons_per_course']);
        $this->assertSame(4, $array['topics_per_lesson']);
        $this->assertSame(2, $array['quizzes_per_lesson']);
    }

    public function test_learndash_ignores_lifter_section_fields(): void
    {
        $config = SeedConfig::fromArray([
            'plugin'              => 'learndash',
            'lessons_per_course'  => 8,
            'sections_per_course' => 3,
            'lessons_per_section' => 4,
            'quizzes_per_lesson'  => 2,
        ]);

        $this->assertSame(8, $config->lessonsPerCourse);
        $this->assertSame(0, $config->sectionsPerCourse);
        $this->assertSame(2, $config->quizzesPerSection);
    }

    public function test_lifter_multiplies_sections_and_lessons_per_section(): void
    {
        $config = SeedConfig::fromArray([
            'plugin'              => 'lifterlms',
            'sections_per_course' => 3,
            'lessons_per_section' => 4,
        ]);

        $this->assertSame(12, $config->lessonsPerCourse);
        $this->assertSame(4, $config->lessonsPerSection);
    }

    public function test_groups_and_password_are_normalized(): void
    {
        $config = SeedConfig::fromArray([
            'plugin'        => 'learndash',
            'groups'        => 3,
            'user_password' => '  SecretPass1!  ',
        ]);

        $this->assertSame(3, $config->groups);
        $this->assertSame('SecretPass1!', $config->userPassword);
        $this->assertSame(3, $config->toArray()['groups']);
        $this->assertSame('SecretPass1!', $config->toArray()['user_password']);
    }

    public function test_empty_password_becomes_null(): void
    {
        $config = SeedConfig::fromArray([
            'plugin'        => 'learndash',
            'user_password' => '   ',
        ]);

        $this->assertNull($config->userPassword);
    }
}
