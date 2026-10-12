<?php
/**
 * Learn Dash Seeder.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Seeding\SeedingIdMap;
use Tangible\Populater\Support\DeterministicTitle;
use Tangible\Populater\Support\DummyContent;

/**
 * Seeder for the LearnDash LMS plugin.
 *
 * Course → lessons (sfwd-lessons) → topics (sfwd-topic) → quiz per lesson (on a topic).
 */
class LearnDashSeeder extends AbstractSeeder {

	/**
	 * Configures free enrollment on a newly created course.
	 *
	 * @param int                  $postId Course post ID.
	 * @param int                  $index One-based course index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterCourseCreated( int $postId, int $index, array $options = array() ): void {
		$this->configureFreeEnrollment( $postId );
	}

	/**
	 * Links a new lesson to its course, seeds its topics and records the topic that will hold the lesson quiz.
	 *
	 * @param int                  $postId Lesson post ID.
	 * @param int                  $courseId Course post ID.
	 * @param int                  $index One-based lesson index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterLessonCreated( int $postId, int $courseId, int $index, array $options = array() ): void {
		if ( $courseId > 0 ) {
			$this->linkStepToCourse( $courseId, $postId, $courseId, $this->getPostType( 'lessons' ) );
		}

		$options['course_id'] = $courseId;
		$topicIds             = $this->seedTopicsForLesson( $postId, $options );

		if ( array() === $topicIds ) {
			return;
		}

		$processId = (string) ( $options['process_id'] ?? '' );

		if ( '' === $processId ) {
			return;
		}

		SeedingIdMap::recordLessonQuizTopic(
			$processId,
			(int) ( $options['course_index'] ?? 0 ),
			$index,
			(int) end( $topicIds ),
			new ProcessRepository(),
		);
	}

	/**
	 * Creates the ProQuiz record for a new quiz and links it to its topic, lesson and course.
	 *
	 * @param int                  $postId Quiz post ID.
	 * @param int                  $parentId Parent topic post ID.
	 * @param int                  $index One-based quiz index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterQuizCreated( int $postId, int $parentId, int $index, array $options = array() ): void {
		$prefix      = $options['title_prefix'] ?? $this->getTitlePrefix( 'quizzes' );
		$courseIndex = DeterministicTitle::courseIndex( $options );
		$index       = DeterministicTitle::resolveIndex( $options, $index );
		$title       = DeterministicTitle::quiz( (string) $prefix, $courseIndex, $options );
		LearnDashProQuizHelper::createProQuiz( $postId, $title );

		$courseId = (int) ( $options['course_id'] ?? 0 );
		$topicId  = (int) ( $options['topic_id'] ?? 0 );
		$lessonId = (int) ( $options['lesson_id'] ?? 0 );

		if ( $topicId <= 0 ) {
			$topicId = (int) ( $options['quiz_parent_id'] ?? $parentId );

			if ( $topicId > 0 && function_exists( 'get_post_type' ) && \get_post_type( $topicId ) !== $this->getPostType( 'topics' ) ) {
				$topicId = 0;
			}
		}

		if ( $lessonId <= 0 && $topicId > 0 ) {
			$lessonId = (int) get_post_meta( $topicId, 'lesson_id', true );
		}

		if ( $courseId <= 0 && $lessonId > 0 ) {
			$courseId = (int) get_post_meta( $lessonId, 'course_id', true );
		}

		if ( $courseId > 0 && $topicId > 0 && $lessonId > 0 ) {
			$this->linkQuizToTopic( $courseId, $postId, $lessonId, $topicId );
		}
	}

	/**
	 * Attaches a new question to its quiz with generated dummy content.
	 *
	 * @param int                  $postId Question post ID.
	 * @param int                  $quizId Quiz post ID.
	 * @param int                  $index One-based question index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterQuestionCreated( int $postId, int $quizId, int $index, array $options = array() ): void {
		$prefix      = $options['title_prefix'] ?? $this->getTitlePrefix( 'questions' );
		$courseIndex = DeterministicTitle::courseIndex( $options );
		$title       = DeterministicTitle::question( (string) $prefix, $courseIndex, $options, $index );
		$content     = DummyContent::question( $title, $index );

		LearnDashProQuizHelper::attachQuestion( $quizId, $postId, $title, $content, $index );
	}

	/**
	 * Seeds topics under a lesson and links each to the course.
	 *
	 * @param int                  $count Number of topics to create.
	 * @param int                  $lessonId Lesson post ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> IDs of the created topics.
	 */
	public function seedTopics( int $count, int $lessonId, array $options = array() ): array {
		$courseId = (int) ( $options['course_id'] ?? 0 );

		if ( $courseId <= 0 && $lessonId > 0 ) {
			$courseId = (int) get_post_meta( $lessonId, 'course_id', true );
		}

		$courseIndex = DeterministicTitle::courseIndex( $options );
		$lessonIndex = DeterministicTitle::resolveIndex( $options, 1 );
		$prefix      = $options['title_prefix'] ?? $this->getTitlePrefix( 'topics' );
		$ids         = array();

		for ( $i = 1; $i <= $count; $i++ ) {
			$title  = DeterministicTitle::topic( (string) $prefix, $courseIndex, $lessonIndex, $i );
			$postId = $this->insertPost(
				array(
					'post_title'   => $title,
					'post_type'    => $this->getPostType( 'topics' ),
					'post_status'  => 'publish',
					'post_content' => DummyContent::topic( $title, $i ),
					'post_excerpt' => DummyContent::excerpt( 'topic', $title, $i ),
				)
			);

			if ( $postId > 0 ) {
				$this->applyMeta(
					$postId,
					$this->getMetaFor(
						'topics',
						array(
							'courseId' => $courseId,
							'lessonId' => $lessonId,
						)
					)
				);

				if ( $courseId > 0 && $lessonId > 0 ) {
					$this->linkStepToCourse( $courseId, $postId, $lessonId, $this->getPostType( 'topics' ) );
				}

				$ids[] = $postId;
			}
		}//end for

		return $ids;
	}

	/**
	 * Seeds quizzes attached to a topic, with their questions.
	 *
	 * @param int                  $count Number of quizzes to create.
	 * @param int                  $parentId Parent post ID, expected to be a topic.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> IDs of the created quizzes.
	 */
	public function seedQuizzes( int $count, int $parentId, array $options = array() ): array {
		$topicId  = (int) ( $options['topic_id'] ?? 0 );
		$lessonId = (int) ( $options['lesson_id'] ?? 0 );
		$courseId = (int) ( $options['course_id'] ?? 0 );

		if ( $topicId <= 0 ) {
			$candidate = (int) ( $options['quiz_parent_id'] ?? $parentId );

			if ( $candidate > 0 && ( ! function_exists( 'get_post_type' ) || \get_post_type( $candidate ) === $this->getPostType( 'topics' ) ) ) {
				$topicId = $candidate;
			}
		}

		if ( $lessonId <= 0 && $topicId > 0 ) {
			$lessonId = (int) get_post_meta( $topicId, 'lesson_id', true );
		}

		if ( $courseId <= 0 && $lessonId > 0 ) {
			$courseId = (int) get_post_meta( $lessonId, 'course_id', true );
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
				)
			);

			if ( $postId > 0 ) {
				$this->applyMeta(
					$postId,
					$this->getMetaFor(
						'quizzes',
						array(
							'lessonId' => $lessonId,
							'courseId' => $courseId,
							'topicId'  => $topicId,
						)
					)
				);
				$this->afterQuizCreated(
					$postId,
					$topicId,
					$index,
					array_merge(
						$options,
						array(
							'course_id'      => $courseId,
							'lesson_id'      => $lessonId,
							'topic_id'       => $topicId,
							'quiz_parent_id' => $topicId,
						)
					)
				);
				$this->seedQuestionsForQuiz( $postId, $options );
				$ids[] = $postId;
			}//end if
		}//end for

		return $ids;
	}

	/**
	 * Seeds the configured number of topics for a lesson.
	 *
	 * @param int                  $lessonId Lesson post ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> IDs of the created topics, empty when none are configured.
	 */
	protected function seedTopicsForLesson( int $lessonId, array $options = array() ): array {
		$count = (int) ( $options['topics_per_lesson'] ?? 0 );

		if ( $count <= 0 || $lessonId <= 0 ) {
			return array();
		}

		return $this->seedTopics( $count, $lessonId, $options );
	}

	/**
	 * Adds a quiz as a child of a topic in the course structure.
	 *
	 * @param int $courseId Course post ID.
	 * @param int $quizId Quiz post ID.
	 * @param int $lessonId Lesson post ID.
	 * @param int $topicId Topic post ID.
	 * @return void
	 */
	private function linkQuizToTopic( int $courseId, int $quizId, int $lessonId, int $topicId ): void {
		if ( $courseId <= 0 || $quizId <= 0 || $lessonId <= 0 || $topicId <= 0 ) {
			return;
		}

		if ( function_exists( 'learndash_course_add_child_to_parent' ) ) {
			\learndash_course_add_child_to_parent( $courseId, $quizId, $topicId );
		}

		$this->linkQuizToTopicFallback( $courseId, $quizId, $lessonId, $topicId );
	}

	/**
	 * Adds a lesson or topic as a child of its parent in the course structure.
	 *
	 * @param int    $courseId Course post ID.
	 * @param int    $childId Child step post ID.
	 * @param int    $parentId Parent step post ID.
	 * @param string $childType Child post type.
	 * @return void
	 */
	private function linkStepToCourse( int $courseId, int $childId, int $parentId, string $childType ): void {
		if ( $courseId <= 0 || $childId <= 0 || $parentId <= 0 || '' === $childType ) {
			return;
		}

		if ( function_exists( 'learndash_course_add_child_to_parent' ) ) {
			\learndash_course_add_child_to_parent( $courseId, $childId, $parentId );
		}

		$this->linkStepToCourseFallback( $courseId, $childId, $parentId, $childType );
	}

	/**
	 * Writes a lesson or topic into the ld_course_steps meta directly.
	 *
	 * @param int    $courseId Course post ID.
	 * @param int    $childId Child step post ID.
	 * @param int    $parentId Parent step post ID.
	 * @param string $childType Child post type.
	 * @return void
	 */
	private function linkStepToCourseFallback( int $courseId, int $childId, int $parentId, string $childType ): void {
		$lessonType = $this->getPostType( 'lessons' );
		$topicType  = $this->getPostType( 'topics' );
		$quizType   = $this->getPostType( 'quizzes' );

		if ( $childType === $quizType || $childType === $topicType ) {
			update_post_meta( $childId, 'course_id', $courseId );
		}

		$steps = get_post_meta( $courseId, 'ld_course_steps', true );

		if ( ! is_array( $steps ) ) {
			$steps = array(
				'steps'     => array( 'h' => array( $lessonType => array() ) ),
				'versions'  => array(),
				'empty'     => false,
				'course_id' => $courseId,
			);
		}

		if ( ! isset( $steps['steps']['h'][ $lessonType ] ) || ! is_array( $steps['steps']['h'][ $lessonType ] ) ) {
			$steps['steps']['h'][ $lessonType ] = array();
		}

		if ( $childType === $lessonType && $parentId === $courseId ) {
			$steps['steps']['h'][ $lessonType ][ $childId ] = array(
				$topicType => array(),
				$quizType  => array(),
			);
		} elseif ( $childType === $topicType ) {
			if ( ! isset( $steps['steps']['h'][ $lessonType ][ $parentId ] ) ) {
				$steps['steps']['h'][ $lessonType ][ $parentId ] = array(
					$topicType => array(),
					$quizType  => array(),
				);
			}

			if ( ! isset( $steps['steps']['h'][ $lessonType ][ $parentId ][ $topicType ] ) || ! is_array( $steps['steps']['h'][ $lessonType ][ $parentId ][ $topicType ] ) ) {
				$steps['steps']['h'][ $lessonType ][ $parentId ][ $topicType ] = array();
			}

			$steps['steps']['h'][ $lessonType ][ $parentId ][ $topicType ][ $childId ] = array();
		}

		$steps['empty'] = false;
		update_post_meta( $courseId, 'ld_course_steps', $steps );
	}

	/**
	 * Writes a quiz under its topic in the ld_course_steps meta directly.
	 *
	 * @param int $courseId Course post ID.
	 * @param int $quizId Quiz post ID.
	 * @param int $lessonId Lesson post ID.
	 * @param int $topicId Topic post ID.
	 * @return void
	 */
	private function linkQuizToTopicFallback( int $courseId, int $quizId, int $lessonId, int $topicId ): void {
		update_post_meta( $quizId, 'course_id', $courseId );

		$steps = get_post_meta( $courseId, 'ld_course_steps', true );

		if ( ! is_array( $steps ) ) {
			$steps = array(
				'steps'     => array( 'h' => array( $this->getPostType( 'lessons' ) => array() ) ),
				'versions'  => array(),
				'empty'     => false,
				'course_id' => $courseId,
			);
		}

		$lessonType = $this->getPostType( 'lessons' );
		$topicType  = $this->getPostType( 'topics' );
		$quizType   = $this->getPostType( 'quizzes' );

		if ( ! isset( $steps['steps']['h'][ $lessonType ] ) || ! is_array( $steps['steps']['h'][ $lessonType ] ) ) {
			$steps['steps']['h'][ $lessonType ] = array();
		}

		if ( ! isset( $steps['steps']['h'][ $lessonType ][ $lessonId ] ) ) {
			$steps['steps']['h'][ $lessonType ][ $lessonId ] = array(
				$topicType => array(),
				$quizType  => array(),
			);
		}

		if ( ! isset( $steps['steps']['h'][ $lessonType ][ $lessonId ][ $topicType ][ $topicId ] ) || ! is_array( $steps['steps']['h'][ $lessonType ][ $lessonId ][ $topicType ][ $topicId ] ) ) {
			$steps['steps']['h'][ $lessonType ][ $lessonId ][ $topicType ][ $topicId ] = array();
		}

		if ( ! isset( $steps['steps']['h'][ $lessonType ][ $lessonId ][ $topicType ][ $topicId ][ $quizType ] ) || ! is_array( $steps['steps']['h'][ $lessonType ][ $lessonId ][ $topicType ][ $topicId ][ $quizType ] ) ) {
			$steps['steps']['h'][ $lessonType ][ $lessonId ][ $topicType ][ $topicId ][ $quizType ] = array();
		}

		$steps['steps']['h'][ $lessonType ][ $lessonId ][ $topicType ][ $topicId ][ $quizType ][ $quizId ] = array();
		$steps['empty'] = false;
		update_post_meta( $courseId, 'ld_course_steps', $steps );
	}

	/**
	 * Grants a group access to a course.
	 *
	 * @param int                  $courseId Course post ID.
	 * @param int                  $groupId Group post ID.
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
		if ( $courseId <= 0 || $groupId <= 0 ) {
			return;
		}

		if ( function_exists( 'ld_update_course_group_access' ) ) {
			\ld_update_course_group_access( $courseId, $groupId );
		}
	}

	/**
	 * Adds a user to a group as a member or a group leader.
	 *
	 * @param int                  $userId User ID.
	 * @param int                  $groupId Group post ID.
	 * @param int                  $groupIndex One-based group index.
	 * @param bool                 $isGroupAdmin Whether the user is a group leader.
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
		if ( $userId <= 0 || $groupId <= 0 ) {
			return;
		}

		if ( $isGroupAdmin ) {
			if ( function_exists( 'learndash_set_groups_administrators' ) ) {
				\learndash_set_groups_administrators( $groupId, array( $userId ) );
			} elseif ( function_exists( 'ld_update_leader_group_access' ) ) {
				\ld_update_leader_group_access( $userId, $groupId );
			}

			return;
		}

		if ( function_exists( 'ld_update_group_access' ) ) {
			\ld_update_group_access( $userId, $groupId );
		}
	}

	/**
	 * Completes all courses for the seeded student at the given index.
	 *
	 * @param int                  $count Unused item count.
	 * @param array<string, mixed> $options Seeding options; "index" selects the student.
	 * @return list<int> The student user ID, or an empty list when nothing was completed.
	 */
	public function seedStudentActivity( int $count, array $options = array() ): array {
		$index    = (int) ( $options['index'] ?? 0 );
		$username = \Tangible\Populater\Support\SeededUsername::username( $this->getSlug(), 'student', $index );
		$user     = function_exists( 'get_user_by' ) ? get_user_by( 'login', $username ) : false;

		if ( ! $user instanceof \WP_User ) {
			return array();
		}

		$completed = LearnDashStudentActivity::completeAllCourses( $user->ID );

		return array() !== $completed ? array( $user->ID ) : array();
	}

	/**
	 * Sets a course to the free price type so students can enroll.
	 *
	 * @param int $courseId Course post ID.
	 * @return void
	 */
	private function configureFreeEnrollment( int $courseId ): void {
		if ( $courseId <= 0 ) {
			return;
		}

		if ( function_exists( 'learndash_update_setting' ) ) {
			\learndash_update_setting( $courseId, 'course_price_type', 'free' );
			\learndash_update_setting( $courseId, 'course_disable_content_table', '' );
		}

		if ( ! function_exists( 'get_post_meta' ) || ! function_exists( 'update_post_meta' ) ) {
			return;
		}

		$settings = get_post_meta( $courseId, '_sfwd-courses', true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['sfwd-courses_course_price_type']            = 'free';
		$settings['sfwd-courses_custom_button_url']            = '';
		$settings['sfwd-courses_course_price']                 = '';
		$settings['sfwd-courses_course_disable_content_table'] = '';
		update_post_meta( $courseId, '_sfwd-courses', $settings );
	}
}
