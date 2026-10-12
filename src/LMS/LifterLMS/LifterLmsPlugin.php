<?php
/**
 * Lifter Lms Plugin.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Registry\LmsContainerEntity;
use Tangible\Populater\Registry\LmsEntitySchema;

/**
 * LifterLMS plugin definition.
 */
class LifterLmsPlugin extends AbstractLmsPlugin {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected string $slug = 'lifterlms';
	/**
	 * Plugin display name.
	 *
	 * @var string
	 */
	protected string $name = 'LifterLMS';
	/**
	 * Main plugin file relative to the plugins directory.
	 *
	 * @var string
	 */
	protected string $pluginFile = 'lifterlms/lifterlms.php';
	/**
	 * Seeder class name.
	 *
	 * @var class-string<\Tangible\Populater\Seeders\AbstractSeeder>
	 */
	protected string $seederClass = LifterLMSSeeder::class;
	/**
	 * Background seeding action name.
	 *
	 * @var string
	 */
	protected string $backgroundAction = 'seed_lifterlms';

	/**
	 * Defines the LifterLMS post types, title prefixes, meta mappings and section container.
	 *
	 * @return LmsEntitySchema The LifterLMS entity schema.
	 */
	protected function defineEntitySchema(): LmsEntitySchema {
		return new LmsEntitySchema(
			postTypes: array(
				'courses'      => 'course',
				'lessons'      => 'lesson',
				'sections'     => 'section',
				'quizzes'      => 'llms_quiz',
				'questions'    => 'llms_question',
				'certificates' => 'llms_certificate',
				'groups'       => 'llms_group',
			),
			titlePrefixes: array(
				'courses'      => 'LifterLMS Course',
				'lessons'      => 'LifterLMS Lesson',
				'sections'     => 'LifterLMS Section',
				'quizzes'      => 'LifterLMS Quiz',
				'questions'    => 'LifterLMS Question',
				'certificates' => 'LifterLMS Certificate',
				'groups'       => 'LifterLMS Group',
			),
			userPrefix: 'lifterlms_user',
			metaMap: array(
				'lessons'   => array( '_llms_parent_course' => 'courseId' ),
				'quizzes'   => array( '_llms_lesson_id' => 'lessonId' ),
				'questions' => array( '_llms_parent_id' => 'quizId' ),
			),
			container: new LmsContainerEntity(
				entity: 'sections',
				cacheMetaKey: '_populater_llms_section_ids',
				parentMetaKey: '_llms_parent_course',
				lessonParentMetaKey: '_llms_parent_section',
			),
			quizParentEntity: 'sections',
		);
	}
}
