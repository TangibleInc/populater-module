<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

/**
 * Predictable true/false answers for LifterLMS stress and load tests.
 *
 * Odd-indexed questions: True (marker A) is correct.
 * Even-indexed questions: False (marker B) is correct.
 */
final class LifterLmsTrueFalseAnswers
{
    public const CORRECT_MARKER_META = '_populater_correct_choice_marker';

    public const TRUE_MARKER  = 'A';
    public const FALSE_MARKER = 'B';

    public const CORRECT_CHOICE_LABEL   = 'correct answer';
    public const INCORRECT_CHOICE_LABEL = 'incorrect answer';

    public static function trueIsCorrect(int $questionIndex): bool
    {
        return $questionIndex % 2 === 1;
    }

    public static function correctMarker(int $questionIndex): string
    {
        return self::trueIsCorrect($questionIndex) ? self::TRUE_MARKER : self::FALSE_MARKER;
    }

    /**
     * @return array{true: array{choice: string, correct: bool, marker: string}, false: array{choice: string, correct: bool, marker: string}}
     */
    public static function choices(int $questionIndex): array
    {
        $trueIsCorrect = self::trueIsCorrect($questionIndex);

        return [
            'true' => [
                'choice'  => $trueIsCorrect ? self::CORRECT_CHOICE_LABEL : self::INCORRECT_CHOICE_LABEL,
                'correct' => $trueIsCorrect,
                'marker'  => self::TRUE_MARKER,
            ],
            'false' => [
                'choice'  => $trueIsCorrect ? self::INCORRECT_CHOICE_LABEL : self::CORRECT_CHOICE_LABEL,
                'correct' => !$trueIsCorrect,
                'marker'  => self::FALSE_MARKER,
            ],
        ];
    }

    public static function stressHint(int $questionIndex): string
    {
        $marker = self::correctMarker($questionIndex);

        return sprintf(
            '<p class="populater-stress-hint" data-correct-marker="%s" data-correct-answer="%s">'
            . 'Stress test: select %s (marker %s).</p>',
            $marker,
            strtolower(self::CORRECT_CHOICE_LABEL),
            self::CORRECT_CHOICE_LABEL,
            $marker,
        );
    }
}
