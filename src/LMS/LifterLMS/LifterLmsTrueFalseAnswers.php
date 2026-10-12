<?php
/**
 * Lifter Lms True False Answers.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

/**
 * Predictable true/false answers for LifterLMS stress and load tests.
 *
 * Odd-indexed questions: True (marker A) is correct.
 * Even-indexed questions: False (marker B) is correct.
 */
final class LifterLmsTrueFalseAnswers {

	public const CORRECT_MARKER_META = '_populater_correct_choice_marker';

	public const TRUE_MARKER  = 'A';
	public const FALSE_MARKER = 'B';

	public const CORRECT_CHOICE_LABEL   = 'correct answer';
	public const INCORRECT_CHOICE_LABEL = 'incorrect answer';

	/**
	 * Tells whether True is the correct answer for a question.
	 *
	 * @param int $questionIndex One-based question index.
	 * @return bool True for odd-indexed questions.
	 */
	public static function trueIsCorrect( int $questionIndex ): bool {
		return 1 === $questionIndex % 2;
	}

	/**
	 * Returns the marker of the correct choice for a question.
	 *
	 * @param int $questionIndex One-based question index.
	 * @return string The marker "A" or "B".
	 */
	public static function correctMarker( int $questionIndex ): string {
		return self::trueIsCorrect( $questionIndex ) ? self::TRUE_MARKER : self::FALSE_MARKER;
	}

	/**
	 * Builds the true and false choices for a question, flagging which is correct.
	 *
	 * @param int $questionIndex One-based question index.
	 * @return array{true: array{choice: string, correct: bool, marker: string}, false: array{choice: string, correct: bool, marker: string}} Choice data keyed by "true" and "false".
	 */
	public static function choices( int $questionIndex ): array {
		$trueIsCorrect = self::trueIsCorrect( $questionIndex );

		return array(
			'true'  => array(
				'choice'  => $trueIsCorrect ? self::CORRECT_CHOICE_LABEL : self::INCORRECT_CHOICE_LABEL,
				'correct' => $trueIsCorrect,
				'marker'  => self::TRUE_MARKER,
			),
			'false' => array(
				'choice'  => $trueIsCorrect ? self::INCORRECT_CHOICE_LABEL : self::CORRECT_CHOICE_LABEL,
				'correct' => ! $trueIsCorrect,
				'marker'  => self::FALSE_MARKER,
			),
		);
	}

	/**
	 * Builds the HTML hint that names the correct choice for stress tests.
	 *
	 * @param int $questionIndex One-based question index.
	 * @return string The hint markup.
	 */
	public static function stressHint( int $questionIndex ): string {
		$marker = self::correctMarker( $questionIndex );

		return sprintf(
			'<p class="populater-stress-hint" data-correct-marker="%s" data-correct-answer="%s">'
			. 'Stress test: select %s (marker %s).</p>',
			$marker,
			strtolower( self::CORRECT_CHOICE_LABEL ),
			self::CORRECT_CHOICE_LABEL,
			$marker,
		);
	}
}
