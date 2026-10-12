<?php
/**
 * Deterministic title.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Builds unique, predictable post titles and slugs for seeded LMS content.
 *
 * Titles encode hierarchy (course / lesson / topic / quiz indices) so k6 and
 * other load tests can derive permalinks without querying the database.
 */
final class DeterministicTitle {

	/**
	 * Builds the title of a seeded course.
	 *
	 * @param string $prefix Title prefix, usually the LMS label.
	 * @param int    $index  One-based course index.
	 * @return string Course title.
	 */
	public static function course( string $prefix, int $index ): string {
		return sprintf( '%s %d', $prefix, $index );
	}

	/**
	 * Builds the title of a seeded lesson, encoding its course and lesson indices.
	 *
	 * @param string $prefix Title prefix, usually the LMS label.
	 * @param int    $courseIndex One-based course index.
	 * @param int    $lessonIndex One-based lesson index.
	 * @return string Lesson title.
	 */
	public static function lesson( string $prefix, int $courseIndex, int $lessonIndex ): string {
		return sprintf( '%s C%d L%d', $prefix, $courseIndex, $lessonIndex );
	}

	/**
	 * Builds the title of a seeded topic, encoding its course, lesson and topic indices.
	 *
	 * @param string $prefix Title prefix, usually the LMS label.
	 * @param int    $courseIndex One-based course index.
	 * @param int    $lessonIndex One-based lesson index.
	 * @param int    $topicIndex  One-based topic index.
	 * @return string Topic title.
	 */
	public static function topic( string $prefix, int $courseIndex, int $lessonIndex, int $topicIndex ): string {
		return sprintf( '%s C%d L%d T%d', $prefix, $courseIndex, $lessonIndex, $topicIndex );
	}

	/**
	 * Builds the title of a seeded quiz, encoding its position under a topic, section, module, lesson or course.
	 *
	 * @param string               $prefix      Title prefix, usually the LMS label.
	 * @param int                  $courseIndex One-based course index.
	 * @param array<string, mixed> $options     Quiz context with optional index, quiz_parent_entity, quiz_parent_index and lesson_index.
	 * @return string Quiz title.
	 */
	public static function quiz( string $prefix, int $courseIndex, array $options ): string {
		$quizIndex    = (int) ( $options['index'] ?? 1 );
		$parentEntity = (string) ( $options['quiz_parent_entity'] ?? '' );
		$parentIndex  = (int) ( $options['quiz_parent_index'] ?? 0 );
		$lessonIndex  = (int) ( $options['lesson_index'] ?? 0 );

		if ( 'topics' === $parentEntity && $parentIndex > 0 && $lessonIndex > 0 ) {
			return sprintf( '%s C%d L%d T%d Q%d', $prefix, $courseIndex, $lessonIndex, $parentIndex, $quizIndex );
		}

		if ( 'sections' === $parentEntity && $parentIndex > 0 ) {
			return sprintf( '%s C%d S%d Q%d', $prefix, $courseIndex, $parentIndex, $quizIndex );
		}

		if ( 'modules' === $parentEntity && $parentIndex > 0 ) {
			return sprintf( '%s C%d M%d Q%d', $prefix, $courseIndex, $parentIndex, $quizIndex );
		}

		if ( $lessonIndex > 0 ) {
			return sprintf( '%s C%d L%d Q%d', $prefix, $courseIndex, $lessonIndex, $quizIndex );
		}

		return sprintf( '%s C%d Q%d', $prefix, $courseIndex, $quizIndex );
	}

	/**
	 * Builds the title of a seeded question by appending its index to the parent quiz title.
	 *
	 * @param string               $prefix        Title prefix, usually the LMS label.
	 * @param int                  $courseIndex   One-based course index.
	 * @param array<string, mixed> $options       Same context as {@see quiz()}.
	 * @param int                  $questionIndex One-based question index.
	 * @return string Question title.
	 */
	public static function question( string $prefix, int $courseIndex, array $options, int $questionIndex ): string {
		return self::quiz( $prefix, $courseIndex, $options ) . ' N' . $questionIndex;
	}

	/**
	 * Builds the title of a seeded section, encoding its course and section indices.
	 *
	 * @param string $prefix Title prefix, usually the LMS label.
	 * @param int    $courseIndex One-based course index.
	 * @param int    $sectionIndex One-based section index.
	 * @return string Section title.
	 */
	public static function section( string $prefix, int $courseIndex, int $sectionIndex ): string {
		return sprintf( '%s C%d S%d', $prefix, $courseIndex, $sectionIndex );
	}

	/**
	 * Builds the title of a seeded module, encoding its course and module indices.
	 *
	 * @param string $prefix Title prefix, usually the LMS label.
	 * @param int    $courseIndex One-based course index.
	 * @param int    $moduleIndex One-based module index.
	 * @return string Module title.
	 */
	public static function module( string $prefix, int $courseIndex, int $moduleIndex ): string {
		return sprintf( '%s C%d M%d', $prefix, $courseIndex, $moduleIndex );
	}

	/**
	 * Builds the title of a seeded group.
	 *
	 * @param string $prefix Title prefix, usually the LMS label.
	 * @param int    $index  One-based group index.
	 * @return string Group title.
	 */
	public static function group( string $prefix, int $index ): string {
		return sprintf( '%s %d', $prefix, $index );
	}

	/**
	 * Builds the title of a seeded certificate.
	 *
	 * @param string $prefix Title prefix, usually the LMS label.
	 * @param int    $index  One-based certificate index.
	 * @return string Certificate title.
	 */
	public static function certificate( string $prefix, int $index ): string {
		return sprintf( '%s %d', $prefix, $index );
	}

	/**
	 * Converts a title to a URL slug, falling back to a plain implementation outside WordPress.
	 *
	 * @param string $title Title to convert.
	 * @return string Slug, or "item" if the title yields none.
	 */
	public static function slug( string $title ): string {
		if ( function_exists( 'sanitize_title' ) ) {
			return sanitize_title( $title );
		}

		$slug = strtolower( $title );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug ) ?? '';

		$slug = trim( $slug, '-' );

		return '' !== $slug ? $slug : 'item';
	}

	/**
	 * Reads the item index from the options, falling back to the loop position.
	 *
	 * @param array<string, mixed> $options   Step options that may contain an index.
	 * @param int                  $loopIndex Index to use when the options have none.
	 * @return int Resolved item index.
	 */
	public static function resolveIndex( array $options, int $loopIndex ): int {
		return (int) ( $options['index'] ?? $loopIndex );
	}

	/**
	 * Reads the course index from the options, never returning a negative value.
	 *
	 * @param array<string, mixed> $options Step options that may contain a course_index.
	 * @return int Course index, or 0 if not set.
	 */
	public static function courseIndex( array $options ): int {
		return max( 0, (int) ( $options['course_index'] ?? 0 ) );
	}
}
