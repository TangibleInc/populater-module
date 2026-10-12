<?php
/**
 * Tangible Lms Plugin.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Registry\LmsContainerEntity;
use Tangible\Populater\Registry\LmsEntitySchema;

/**
 * Tangible LMS plugin definition.
 */
class TangibleLmsPlugin extends AbstractLmsPlugin {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected string $slug = 'tangible-lms';
	/**
	 * Plugin display name.
	 *
	 * @var string
	 */
	protected string $name = 'Tangible LMS';
	/**
	 * Main plugin file relative to the plugins directory.
	 *
	 * @var string
	 */
	protected string $pluginFile = 'tangible-lms/tangible-lms.php';
	/**
	 * Seeder class name.
	 *
	 * @var class-string<\Tangible\Populater\Seeders\AbstractSeeder>
	 */
	protected string $seederClass = TangibleLMSSeeder::class;
	/**
	 * Background seeding action name.
	 *
	 * @var string
	 */
	protected string $backgroundAction = 'seed_tangible_lms';

	/**
	 * Defines the Tangible LMS post types, title prefixes, meta mappings and module container.
	 *
	 * @return LmsEntitySchema The Tangible LMS entity schema.
	 */
	protected function defineEntitySchema(): LmsEntitySchema {
		return new LmsEntitySchema(
			postTypes: array(
				'courses'      => 'tgl_course',
				'modules'      => 'tgl_module',
				'lessons'      => 'tgl_lesson',
				'quizzes'      => 'tgl_quiz',
				'questions'    => 'tgl_question',
				'certificates' => 'tgl_certificate',
			),
			titlePrefixes: array(
				'courses'      => 'Tangible Course',
				'modules'      => 'Tangible Module',
				'lessons'      => 'Tangible Lesson',
				'quizzes'      => 'Tangible Quiz',
				'questions'    => 'Tangible Question',
				'certificates' => 'Tangible Certificate',
			),
			userPrefix: 'tangible_lms_user',
			metaMap: array(
				'lessons'   => array(
					'_tgl_course_id' => 'courseId',
					'_tgl_module_id' => 'moduleId',
				),
				'quizzes'   => array(
					'_tgl_module_id' => 'moduleId',
					'_tgl_course_id' => 'courseId',
				),
				'questions' => array( '_tgl_quiz_id' => 'quizId' ),
			),
			container: new LmsContainerEntity(
				entity: 'modules',
				cacheMetaKey: '_populater_tgl_module_ids',
				parentMetaKey: '_tgl_course_id',
			),
			quizParentEntity: 'modules',
		);
	}
}
