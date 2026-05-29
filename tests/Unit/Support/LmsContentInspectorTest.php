<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Support;

use Tangible\Populater\Tests\Support\LmsContentInspector;

class LmsContentInspectorTest extends \WPTestCase
{
    public function test_expected_counts_includes_nested_structure(): void
    {
        $expected = LmsContentInspector::expectedCounts(
            courses: 2,
            lessonsPerCourse: 3,
            quizzesPerLesson: 2,
            users: 5,
            questionsPerQuiz: 3,
            topicsPerLesson: 2,
            sectionsPerCourse: 1,
            modulesPerCourse: 1,
        );

        $this->assertSame(2, $expected['courses']);
        $this->assertSame(6, $expected['lessons']);
        $this->assertSame(12, $expected['topics']);
        $this->assertSame(12, $expected['quizzes']);
        $this->assertSame(36, $expected['questions']);
        $this->assertSame(2, $expected['sections']);
        $this->assertSame(2, $expected['modules']);
        $this->assertSame(5, $expected['users']);
    }

    public function test_expected_counts_scales_sections_and_modules(): void
    {
        $expected = LmsContentInspector::expectedCounts(
            courses: 1,
            lessonsPerCourse: 6,
            quizzesPerLesson: 1,
            users: 0,
            sectionsPerCourse: 3,
            modulesPerCourse: 2,
        );

        $this->assertSame(3, $expected['sections']);
        $this->assertSame(2, $expected['modules']);
        $this->assertSame(12, $expected['topics']);
    }
}
