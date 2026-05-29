<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Support;

use Tangible\Populater\Tests\Support\LmsContentInspector;

class LmsContentInspectorTest extends \WPTestCase
{
    public function test_expected_counts_includes_nested_structure_for_learndash(): void
    {
        $expected = LmsContentInspector::expectedCounts(
            'learndash',
            courses: 2,
            lessonsPerCourse: 3,
            quizzesPerSection: 2,
            users: 5,
            questionsPerQuiz: 3,
            topicsPerLesson: 2,
        );

        $this->assertSame(2, $expected['courses']);
        $this->assertSame(6, $expected['lessons']);
        $this->assertSame(12, $expected['topics']);
        $this->assertSame(24, $expected['quizzes']);
        $this->assertSame(72, $expected['questions']);
        $this->assertSame(5, $expected['users']);
    }

    public function test_expected_counts_scales_sections_and_modules(): void
    {
        $lifter = LmsContentInspector::expectedCounts(
            'lifterlms',
            courses: 1,
            lessonsPerCourse: 6,
            quizzesPerSection: 2,
            users: 0,
            sectionsPerCourse: 3,
        );

        $this->assertSame(3, $lifter['sections']);
        $this->assertSame(6, $lifter['quizzes']);

        $tangible = LmsContentInspector::expectedCounts(
            'tangible-lms',
            courses: 1,
            lessonsPerCourse: 6,
            quizzesPerSection: 1,
            users: 0,
            modulesPerCourse: 2,
        );

        $this->assertSame(2, $tangible['modules']);
        $this->assertSame(2, $tangible['quizzes']);
    }
}
