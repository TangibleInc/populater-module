<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\LMS;

use Tangible\Populater\LMS\LifterLMS\LifterLmsTrueFalseAnswers;

class LifterLmsTrueFalseAnswersTest extends \WPTestCase
{
    public function test_odd_questions_have_true_as_correct(): void
    {
        $choices = LifterLmsTrueFalseAnswers::choices(1);

        $this->assertTrue($choices['true']['correct']);
        $this->assertFalse($choices['false']['correct']);
        $this->assertSame('correct answer', $choices['true']['choice']);
        $this->assertSame('incorrect answer', $choices['false']['choice']);
        $this->assertSame('A', LifterLmsTrueFalseAnswers::correctMarker(1));
    }

    public function test_even_questions_have_false_as_correct(): void
    {
        $choices = LifterLmsTrueFalseAnswers::choices(2);

        $this->assertFalse($choices['true']['correct']);
        $this->assertTrue($choices['false']['correct']);
        $this->assertSame('incorrect answer', $choices['true']['choice']);
        $this->assertSame('correct answer', $choices['false']['choice']);
        $this->assertSame('B', LifterLmsTrueFalseAnswers::correctMarker(2));
    }

    public function test_stress_hint_includes_marker_and_answer(): void
    {
        $hint = LifterLmsTrueFalseAnswers::stressHint(2);

        $this->assertStringContainsString('data-correct-marker="B"', $hint);
        $this->assertStringContainsString('data-correct-answer="correct answer"', $hint);
        $this->assertStringContainsString('select correct answer (marker B)', $hint);
    }
}
