<?php
/**
 * Dummy content.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Generates readable placeholder HTML for seeded LMS posts.
 */
final class DummyContent {

	/**
	 * Generates placeholder HTML for a course description.
	 *
	 * @param string $label Display label of the LMS content type.
	 * @param int    $index Index of the item.
	 * @return string HTML markup.
	 */
	public static function course( string $label, int $index ): string {
		return self::wrap(
			$label,
			$index,
			<<<HTML
<p>Welcome to this sample course. Use it to explore layouts, navigation, and enrollment during development and QA.</p>
<h3>What you will learn</h3>
<ul>
<li>Core concepts covered in {$label} {$index}</li>
<li>How lessons, assessments, and progress tracking work together</li>
<li>How course structure appears in the admin and front end</li>
</ul>
<p>This content was generated automatically by Tangible Populator.</p>
HTML
		);
	}

	/**
	 * Generates placeholder HTML for a lesson body.
	 *
	 * @param string $label Display label of the LMS content type.
	 * @param int    $index Index of the item.
	 * @return string HTML markup.
	 */
	public static function lesson( string $label, int $index ): string {
		return self::wrap(
			$label,
			$index,
			<<<HTML
<p>This lesson introduces key ideas for {$label} {$index} with enough body copy to render realistically in editors and templates.</p>
<h3>Lesson outline</h3>
<ol>
<li>Overview and learning objectives</li>
<li>Guided walkthrough with sample material</li>
<li>Summary and next steps</li>
</ol>
HTML
		);
	}

	/**
	 * Generates placeholder HTML for a topic body.
	 *
	 * @param string $label Display label of the LMS content type.
	 * @param int    $index Index of the item.
	 * @return string HTML markup.
	 */
	public static function topic( string $label, int $index ): string {
		return self::wrap(
			$label,
			$index,
			<<<HTML
<p>This topic breaks the parent lesson into a focused segment. It includes sample paragraphs so topic navigation and completion UI have content to display.</p>
<p>Key point {$index}: review the material, then continue to the next step in the course.</p>
HTML
		);
	}

	/**
	 * Generates placeholder HTML for a section description.
	 *
	 * @param string $label Display label of the LMS content type.
	 * @param int    $index Index of the item.
	 * @return string HTML markup.
	 */
	public static function section( string $label, int $index ): string {
		return self::wrap(
			$label,
			$index,
			<<<HTML
<p>This section groups related lessons inside the course. {$label} {$index} helps organize the syllabus for testing course builders and student views.</p>
HTML
		);
	}

	/**
	 * Generates placeholder HTML for a module description.
	 *
	 * @param string $label Display label of the LMS content type.
	 * @param int    $index Index of the item.
	 * @return string HTML markup.
	 */
	public static function module( string $label, int $index ): string {
		return self::wrap(
			$label,
			$index,
			<<<HTML
<p>This module organizes lessons within the course. {$label} {$index} provides a container for syllabus navigation and progress tracking in Tangible LMS.</p>
HTML
		);
	}

	/**
	 * Generates placeholder HTML for a group description.
	 *
	 * @param string $label Display label of the LMS content type.
	 * @param int    $index Index of the item.
	 * @return string HTML markup.
	 */
	public static function group( string $label, int $index ): string {
		return self::wrap(
			$label,
			$index,
			<<<'HTML'
<p>This group organizes learners and courses for testing cohort management, group leaders, and access control.</p>
HTML
		);
	}

	/**
	 * Generates placeholder HTML for a quiz description.
	 *
	 * @param string $label Display label of the LMS content type.
	 * @param int    $index Index of the item.
	 * @return string HTML markup.
	 */
	public static function quiz( string $label, int $index ): string {
		return self::wrap(
			$label,
			$index,
			<<<HTML
<p>Complete this quiz to check your understanding. {$label} {$index} includes seeded questions for testing attempts, scoring, and review flows.</p>
HTML
		);
	}

	/**
	 * Generates placeholder HTML for a question prompt.
	 *
	 * @param string $label Display label of the LMS content type.
	 * @param int    $index Index of the item.
	 * @return string HTML markup.
	 */
	public static function question( string $label, int $index ): string {
		return self::wrap(
			$label,
			$index,
			<<<HTML
<p>Which statement best describes the sample material in {$label} {$index}?</p>
HTML
		);
	}

	/**
	 * Generates a plain-text placeholder excerpt for a seeded post.
	 *
	 * @param string $entity Entity type name, such as course or lesson.
	 * @param string $label  Display label of the LMS content type.
	 * @param int    $index  Index of the item.
	 * @return string Excerpt text.
	 */
	public static function excerpt( string $entity, string $label, int $index ): string {
		return sprintf(
			'Sample %s excerpt for %s %d — generated by Tangible Populator.',
			$entity,
			$label,
			$index,
		);
	}

	/**
	 * Prefixes a body with an escaped heading made of the label and index.
	 *
	 * @param string $label Display label of the LMS content type.
	 * @param int    $index Index of the item.
	 * @param string $body  HTML body to place below the heading.
	 * @return string HTML markup.
	 */
	private static function wrap( string $label, int $index, string $body ): string {
		$heading = htmlspecialchars( sprintf( '%s %d', $label, $index ), ENT_QUOTES, 'UTF-8' );

		return "<h2>{$heading}</h2>\n{$body}";
	}
}
