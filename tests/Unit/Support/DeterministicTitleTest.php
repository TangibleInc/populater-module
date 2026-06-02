<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Support;

use Tangible\Populater\Support\DeterministicTitle;

class DeterministicTitleTest extends \WPTestCase
{
    public function test_course_title_is_indexed(): void
    {
        $this->assertSame('LearnDash Course 3', DeterministicTitle::course('LearnDash Course', 3));
    }

    public function test_lesson_title_includes_course_and_lesson_indices(): void
    {
        $this->assertSame('LearnDash Lesson C2 L4', DeterministicTitle::lesson('LearnDash Lesson', 2, 4));
    }

    public function test_quiz_title_varies_by_parent_entity(): void
    {
        $base = ['index' => 1, 'course_index' => 2, 'lesson_index' => 3];

        $this->assertSame(
            'LearnDash Quiz C2 L3 T1 Q1',
            DeterministicTitle::quiz('LearnDash Quiz', 2, array_merge($base, [
                'quiz_parent_entity' => 'topics',
                'quiz_parent_index'  => 1,
            ])),
        );

        $this->assertSame(
            'LifterLMS Quiz C2 S1 Q1',
            DeterministicTitle::quiz('LifterLMS Quiz', 2, array_merge($base, [
                'quiz_parent_entity' => 'sections',
                'quiz_parent_index'  => 1,
                'lesson_index'       => 0,
            ])),
        );

        $this->assertSame(
            'Tangible Quiz C2 M1 Q1',
            DeterministicTitle::quiz('Tangible Quiz', 2, array_merge($base, [
                'quiz_parent_entity' => 'modules',
                'quiz_parent_index'  => 1,
                'lesson_index'       => 0,
            ])),
        );
    }

    public function test_slug_lowercases_and_hyphenates(): void
    {
        $this->assertSame('learndash-lesson-c2-l4', DeterministicTitle::slug('LearnDash Lesson C2 L4'));
    }

    public function test_resolve_index_prefers_options(): void
    {
        $this->assertSame(5, DeterministicTitle::resolveIndex(['index' => 5], 1));
        $this->assertSame(2, DeterministicTitle::resolveIndex([], 2));
    }
}
