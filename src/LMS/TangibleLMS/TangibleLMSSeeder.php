<?php
/**
 * Tangible L M S Seeder.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Seeding\SeedingIdMap;
use Tangible\Populater\Support\DeterministicTitle;
use Tangible\Populater\Support\DummyContent;

/**
 * Seeder for the Tangible LMS plugin.
 *
 * Creates tgl_module posts per course so lessons attach to valid course structure.
 */
class TangibleLMSSeeder extends AbstractSeeder {

	/**
	 * Seeds a lesson under the module of its course, creating that module when needed.
	 *
	 * @param int                  $count Number of lessons requested; one lesson is created per call.
	 * @param int                  $courseId Course post ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> IDs of the created lessons.
	 */
	public function seedLessons( int $count, int $courseId, array $options = array() ): array {
		$schema           = $this->plugin->getEntitySchema();
		$lessonIndex      = DeterministicTitle::resolveIndex( $options, 1 );
		$courseIndex      = DeterministicTitle::courseIndex( $options );
		$lessonsPerCourse = max( 1, (int) ( $options['lessons_per_course'] ?? 1 ) );
		$modulesPerCourse = max( 1, (int) ( $options['modules_per_course'] ?? 1 ) );
		$moduleIndex      = $this->resolveContainerIndex( $lessonIndex, $lessonsPerCourse, $modulesPerCourse );
		$moduleId         = $this->ensureModuleForCourse( $courseId, $moduleIndex, $options );

		$prefix = $options['title_prefix'] ?? $this->getTitlePrefix( 'lessons' );
		$title  = DeterministicTitle::lesson( (string) $prefix, $courseIndex, $lessonIndex );
		$postId = $this->insertPost(
			array(
				'post_title'   => $title,
				'post_type'    => $this->getPostType( 'lessons' ),
				'post_status'  => 'publish',
				'post_content' => DummyContent::lesson( $title, $lessonIndex ),
				'post_excerpt' => DummyContent::excerpt( 'lesson', $title, $lessonIndex ),
				'post_parent'  => $moduleId > 0 ? $moduleId : $courseId,
			)
		);

		if ( $postId <= 0 ) {
			return array();
		}

		$this->applyMeta(
			$postId,
			$this->getMetaFor(
				'lessons',
				array(
					'courseId' => $courseId,
					'moduleId' => $moduleId,
				)
			)
		);

		return array( $postId );
	}

	/**
	 * Seeds quizzes for a module, with their questions.
	 *
	 * @param int                  $count Number of quizzes to create.
	 * @param int                  $parentId Parent module post ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> IDs of the created quizzes.
	 */
	public function seedQuizzes( int $count, int $parentId, array $options = array() ): array {
		$moduleId = (int) ( $options['quiz_parent_id'] ?? $parentId );
		$courseId = (int) ( $options['course_id'] ?? 0 );

		if ( $courseId <= 0 && $moduleId > 0 ) {
			$courseId = (int) get_post_meta( $moduleId, '_tgl_course_id', true );
		}

		$ids         = array();
		$prefix      = $options['title_prefix'] ?? $this->getTitlePrefix( 'quizzes' );
		$courseIndex = DeterministicTitle::courseIndex( $options );

		for ( $i = 1; $i <= $count; $i++ ) {
			$index  = DeterministicTitle::resolveIndex( $options, $i );
			$title  = DeterministicTitle::quiz( (string) $prefix, $courseIndex, $options );
			$postId = $this->insertPost(
				array(
					'post_title'   => $title,
					'post_type'    => $this->getPostType( 'quizzes' ),
					'post_status'  => 'publish',
					'post_content' => DummyContent::quiz( $title, $index ),
					'post_parent'  => $moduleId > 0 ? $moduleId : 0,
				)
			);

			if ( $postId > 0 ) {
				$this->applyMeta(
					$postId,
					$this->getMetaFor(
						'quizzes',
						array(
							'moduleId' => $moduleId,
							'courseId' => $courseId,
						)
					)
				);
				$this->afterQuizCreated( $postId, $moduleId, $index, $options );
				$this->seedQuestionsForQuiz( $postId, $options );
				$ids[] = $postId;
			}
		}//end for

		return $ids;
	}

	/**
	 * Returns the module for a course index, creating and caching it when missing.
	 *
	 * @param int                  $courseId Course post ID.
	 * @param int                  $moduleIndex One-based module index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return int The module post ID, or 0 when modules are not supported or creation failed.
	 */
	private function ensureModuleForCourse( int $courseId, int $moduleIndex, array $options ): int {
		$schema    = $this->plugin->getEntitySchema();
		$container = $schema->container;

		if ( null === $container ) {
			return 0;
		}

		$existing = (int) ( $options['module_id'] ?? 0 );

		if ( $existing > 0 ) {
			return $existing;
		}

		$cached = get_post_meta( $courseId, $container->cacheMetaKey, true );

		if ( is_array( $cached ) && isset( $cached[ $moduleIndex ] ) ) {
			return (int) $cached[ $moduleIndex ];
		}

		$courseIndex = DeterministicTitle::courseIndex( $options );
		$prefix      = $this->getTitlePrefix( $container->entity );
		$title       = DeterministicTitle::module( $prefix, $courseIndex, $moduleIndex );
		$moduleId    = $this->insertPost(
			array(
				'post_title'   => $title,
				'post_type'    => $schema->getPostType( $container->entity ),
				'post_status'  => 'publish',
				'post_content' => DummyContent::module( $title, $moduleIndex ),
				'post_excerpt' => DummyContent::excerpt( 'module', $title, $moduleIndex ),
				'post_parent'  => $courseId,
			)
		);

		if ( $moduleId > 0 ) {
			update_post_meta( $moduleId, $container->parentMetaKey, $courseId );

			if ( ! is_array( $cached ) ) {
				$cached = array();
			}

			$cached[ $moduleIndex ] = $moduleId;
			update_post_meta( $courseId, $container->cacheMetaKey, $cached );

			$this->recordModuleInIdMap( $moduleId, $moduleIndex, $options );
		}

		return $moduleId;
	}

	/**
	 * Records a module as a quiz parent in the process ID map.
	 *
	 * @param int                  $moduleId Module post ID.
	 * @param int                  $moduleIndex One-based module index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	private function recordModuleInIdMap( int $moduleId, int $moduleIndex, array $options ): void {
		$processId = (string) ( $options['process_id'] ?? '' );

		if ( '' === $processId ) {
			return;
		}

		SeedingIdMap::recordQuizParent(
			$processId,
			'modules',
			(int) ( $options['course_index'] ?? 0 ),
			$moduleIndex,
			$moduleId,
			new ProcessRepository(),
		);
	}

	/**
	 * Maps an item index to the one-based index of the container that holds it.
	 *
	 * @param int $itemIndex One-based item index.
	 * @param int $itemsPerContainer Total number of items.
	 * @param int $containers Number of containers.
	 * @return int The one-based container index.
	 */
	private function resolveContainerIndex( int $itemIndex, int $itemsPerContainer, int $containers ): int {
		$containers = max( 1, min( $containers, $itemsPerContainer ) );

		return (int) min( $containers, max( 1, (int) ceil( $itemIndex * $containers / $itemsPerContainer ) ) );
	}

	/**
	 * Stores the group ID and index on a course as meta.
	 *
	 * @param int                  $courseId Course post ID.
	 * @param int                  $groupId Group ID.
	 * @param int                  $groupIndex One-based group index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function assignCourseToGroup(
		int $courseId,
		int $groupId,
		int $groupIndex,
		array $options = array(),
	): void {
		if ( $courseId <= 0 ) {
			return;
		}

		if ( $groupId > 0 ) {
			update_post_meta( $courseId, '_populater_group_id', $groupId );
		}

		if ( $groupIndex > 0 ) {
			update_post_meta( $courseId, '_populater_group_index', $groupIndex );
		}
	}

	/**
	 * Stores the group ID, index and admin flag on a user as meta.
	 *
	 * @param int                  $userId User ID.
	 * @param int                  $groupId Group ID.
	 * @param int                  $groupIndex One-based group index.
	 * @param bool                 $isGroupAdmin Whether the user is a group admin.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function assignUserToGroup(
		int $userId,
		int $groupId,
		int $groupIndex,
		bool $isGroupAdmin,
		array $options = array(),
	): void {
		if ( $userId <= 0 ) {
			return;
		}

		if ( $groupId > 0 ) {
			update_user_meta( $userId, '_populater_group_id', $groupId );
		}

		if ( $groupIndex > 0 ) {
			update_user_meta( $userId, '_populater_group_index', $groupIndex );
		}

		if ( $isGroupAdmin && $groupIndex > 0 ) {
			update_user_meta( $userId, '_populater_group_admin_index', $groupIndex );
		}
	}
}
