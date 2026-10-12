<?php
/**
 * Learn Dash Lms Plugin.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Registry\LmsEntitySchema;

/**
 * LearnDash plugin definition.
 */
class LearnDashLmsPlugin extends AbstractLmsPlugin {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected string $slug = 'learndash';
	/**
	 * Plugin display name.
	 *
	 * @var string
	 */
	protected string $name = 'LearnDash LMS';
	/**
	 * Main plugin file relative to the plugins directory.
	 *
	 * @var string
	 */
	protected string $pluginFile = 'sfwd-lms/sfwd_lms.php';
	/**
	 * Seeder class name.
	 *
	 * @var class-string<\Tangible\Populater\Seeders\AbstractSeeder>
	 */
	protected string $seederClass = LearnDashSeeder::class;
	/**
	 * Background seeding action name.
	 *
	 * @var string
	 */
	protected string $backgroundAction = 'seed_learndash';

	/**
	 * Defines the LearnDash post types, title prefixes, meta mappings and quiz parent entity.
	 *
	 * @return LmsEntitySchema The LearnDash entity schema.
	 */
	protected function defineEntitySchema(): LmsEntitySchema {
		return new LmsEntitySchema(
			postTypes: array(
				'courses'      => 'sfwd-courses',
				'lessons'      => 'sfwd-lessons',
				'topics'       => 'sfwd-topic',
				'quizzes'      => 'sfwd-quiz',
				'questions'    => 'sfwd-question',
				'certificates' => 'sfwd-certificates',
				'groups'       => 'groups',
			),
			titlePrefixes: array(
				'courses'      => 'LearnDash Course',
				'lessons'      => 'LearnDash Lesson',
				'topics'       => 'LearnDash Topic',
				'quizzes'      => 'LearnDash Quiz',
				'questions'    => 'LearnDash Question',
				'certificates' => 'LearnDash Certificate',
				'groups'       => 'LearnDash Group',
			),
			userPrefix: 'learndash_user',
			metaMap: array(
				'lessons'   => array( 'course_id' => 'courseId' ),
				'topics'    => array(
					'course_id' => 'courseId',
					'lesson_id' => 'lessonId',
				),
				'quizzes'   => array(
					'lesson_id' => 'lessonId',
					'course_id' => 'courseId',
					'topic_id'  => 'topicId',
				),
				'questions' => array( 'quiz_id' => 'quizId' ),
			),
			quizParentEntity: 'lessons',
		);
	}
}
