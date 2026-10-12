<?php
/**
 * Lifter L M S Seeder.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Seeders\AbstractSeeder;
use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Seeding\SeedingIdMap;
use Tangible\Populater\Support\DeterministicTitle;
use Tangible\Populater\Support\DummyContent;

/**
 * Seeder for the LifterLMS plugin.
 *
 * Creates section posts per course so lessons attach to valid course structure.
 */
class LifterLMSSeeder extends AbstractSeeder {

	private const COURSE_BLOCKS = <<<'HTML'


<!-- wp:llms/pricing-table /-->

<!-- wp:llms/course-syllabus /-->
HTML;

	/**
	 * Adds a free access plan and the pricing and syllabus blocks to a new course.
	 *
	 * @param int                  $postId Course post ID.
	 * @param int                  $index One-based course index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterCourseCreated( int $postId, int $index, array $options = array() ): void {
		LifterLmsEnrollmentSetup::seedCourseEnrollment( $postId, $index );
		$this->appendCourseBlocks( $postId );
	}

	/**
	 * Seeds a lesson under the section of its course, creating that section when needed.
	 *
	 * @param int                  $count Number of lessons requested; one lesson is created per call.
	 * @param int                  $courseId Course post ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> IDs of the created lessons.
	 */
	public function seedLessons( int $count, int $courseId, array $options = array() ): array {
		$schema            = $this->plugin->getEntitySchema();
		$container         = $schema->container;
		$lessonIndex       = DeterministicTitle::resolveIndex( $options, 1 );
		$courseIndex       = DeterministicTitle::courseIndex( $options );
		$lessonsPerCourse  = max( 1, (int) ( $options['lessons_per_course'] ?? 1 ) );
		$sectionsPerCourse = max( 1, (int) ( $options['sections_per_course'] ?? 1 ) );
		$sectionIndex      = $this->resolveContainerIndex( $lessonIndex, $lessonsPerCourse, $sectionsPerCourse );
		$sectionId         = $this->ensureSectionForCourse( $courseId, $sectionIndex, $options );

		$prefix = $options['title_prefix'] ?? $this->getTitlePrefix( 'lessons' );
		$title  = DeterministicTitle::lesson( (string) $prefix, $courseIndex, $lessonIndex );
		$postId = $this->insertPost(
			array(
				'post_title'   => $title,
				'post_type'    => $this->getPostType( 'lessons' ),
				'post_status'  => 'publish',
				'post_content' => DummyContent::lesson( $title, $lessonIndex ),
				'post_excerpt' => DummyContent::excerpt( 'lesson', $title, $lessonIndex ),
				'post_parent'  => $sectionId > 0 ? $sectionId : $courseId,
			)
		);

		if ( $postId <= 0 ) {
			return array();
		}

		$this->applyMeta( $postId, $this->getMetaFor( 'lessons', array( 'courseId' => $courseId ) ) );

		if ( $sectionId > 0 && null !== $container && '' !== $container->lessonParentMetaKey ) {
			update_post_meta( $postId, $container->lessonParentMetaKey, $sectionId );
		}

		update_post_meta( $postId, '_llms_order', $lessonIndex );

		$this->recordLessonForSection( $sectionIndex, $postId, $options );

		return array( $postId );
	}

	/**
	 * Records a lesson in the process ID map under its section.
	 *
	 * @param int                  $sectionIndex One-based section index.
	 * @param int                  $lessonId Lesson post ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	private function recordLessonForSection( int $sectionIndex, int $lessonId, array $options ): void {
		$processId = (string) ( $options['process_id'] ?? '' );

		if ( '' === $processId ) {
			return;
		}

		SeedingIdMap::recordSectionLesson(
			$processId,
			(int) ( $options['course_index'] ?? 0 ),
			$sectionIndex,
			$lessonId,
			new ProcessRepository(),
		);
	}

	/**
	 * Seeds quizzes for a section and attaches each to a lesson, with its questions.
	 *
	 * @param int                  $count Number of quizzes to create.
	 * @param int                  $parentId Parent section post ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> IDs of the created quizzes.
	 */
	public function seedQuizzes( int $count, int $parentId, array $options = array() ): array {
		$sectionId = (int) ( $options['quiz_parent_id'] ?? $parentId );
		$lessonId  = (int) ( $options['lesson_id'] ?? 0 );

		if ( $lessonId <= 0 && $sectionId > 0 ) {
			$lessonId = $this->resolveLessonForSectionQuiz( $sectionId, (int) ( $options['index'] ?? 1 ) );
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
				$this->applyMeta( $postId, $this->getMetaFor( 'quizzes', array( 'lessonId' => $lessonId ) ) );
				$this->afterQuizCreated(
					$postId,
					$lessonId,
					$index,
					array_merge(
						$options,
						array(
							'lesson_id'      => $lessonId,
							'quiz_parent_id' => $sectionId,
						)
					)
				);
				$this->seedQuestionsForQuiz( $postId, $options );
				$ids[] = $postId;
			}
		}//end for

		return $ids;
	}

	/**
	 * Attaches a new quiz to its lesson when the lesson has no quiz yet.
	 *
	 * @param int                  $postId Quiz post ID.
	 * @param int                  $parentId Parent lesson post ID.
	 * @param int                  $index One-based quiz index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterQuizCreated( int $postId, int $parentId, int $index, array $options = array() ): void {
		$lessonId = (int) ( $options['lesson_id'] ?? $parentId );

		if ( $lessonId <= 0 || $postId <= 0 ) {
			return;
		}

		if ( (int) get_post_meta( $lessonId, '_llms_quiz', true ) > 0 ) {
			return;
		}

		update_post_meta( $lessonId, '_llms_quiz', $postId );
		update_post_meta( $lessonId, '_llms_quiz_enabled', 'yes' );
	}

	/**
	 * Configures a new question as a true/false question with predictable answers and a stress hint.
	 *
	 * @param int                  $postId Question post ID.
	 * @param int                  $quizId Quiz post ID.
	 * @param int                  $index One-based question index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterQuestionCreated( int $postId, int $quizId, int $index, array $options = array() ): void {
		update_post_meta( $postId, '_llms_question_type', 'true_false' );
		update_post_meta( $postId, '_llms_points', 1 );
		update_post_meta( $postId, LifterLmsTrueFalseAnswers::CORRECT_MARKER_META, LifterLmsTrueFalseAnswers::correctMarker( $index ) );
		$this->attachTrueFalseChoices( $postId, $index );
		$this->appendStressHintToQuestion( $postId, $index );

		if ( $index > 0 ) {
			wp_update_post(
				array(
					'ID'         => $postId,
					'menu_order' => $index,
				)
			);
		}
	}

	/**
	 * Appends the stress test hint to the question content unless it is already present.
	 *
	 * @param int $questionId Question post ID.
	 * @param int $index One-based question index.
	 * @return void
	 */
	private function appendStressHintToQuestion( int $questionId, int $index ): void {
		$post = get_post( $questionId );

		if ( null === $post || 'llms_question' !== $post->post_type ) {
			return;
		}

		$hint = LifterLmsTrueFalseAnswers::stressHint( $index );

		if ( str_contains( (string) $post->post_content, 'populater-stress-hint' ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $questionId,
				'post_content' => rtrim( (string) $post->post_content ) . "\n" . $hint,
			)
		);
	}

	/**
	 * Creates the true and false choices for a question.
	 *
	 * @param int $questionId Question post ID.
	 * @param int $questionIndex One-based question index.
	 * @return void
	 */
	private function attachTrueFalseChoices( int $questionId, int $questionIndex ): void {
		if ( $questionId <= 0 ) {
			return;
		}

		$choices = LifterLmsTrueFalseAnswers::choices( $questionIndex );

		if ( function_exists( 'llms_get_post' ) ) {
			$question = llms_get_post( $questionId );

			if ( $question instanceof \LLMS_Question && count( $question->get_choices() ) === 0 ) {
				foreach ( array( 'true', 'false' ) as $key ) {
					$choice = $choices[ $key ];
					$question->create_choice(
						array(
							'choice'      => $choice['choice'],
							'correct'     => $choice['correct'],
							'choice_type' => 'text',
							'marker'      => $choice['marker'],
						)
					);
				}

				return;
			}
		}

		foreach ( array(
			'true'  => 'a',
			'false' => 'b',
		) as $key => $choiceId ) {
			$choice = $choices[ $key ];
			$this->storeTrueFalseChoiceMeta(
				$questionId,
				$choiceId,
				array(
					'id'          => $choiceId,
					'choice'      => $choice['choice'],
					'choice_type' => 'text',
					'correct'     => $choice['correct'],
					'marker'      => $choice['marker'],
					'question_id' => $questionId,
				)
			);
		}
	}

	/**
	 * Stores a choice as question post meta.
	 *
	 * @param int                  $questionId Question post ID.
	 * @param string               $choiceId Choice ID.
	 * @param array<string, mixed> $data Choice data.
	 * @return void
	 */
	private function storeTrueFalseChoiceMeta( int $questionId, string $choiceId, array $data ): void {
		update_post_meta( $questionId, '_llms_choice_' . $choiceId, $data );
	}

	/**
	 * Returns the section for a course index, creating and caching it when missing.
	 *
	 * @param int                  $courseId Course post ID.
	 * @param int                  $sectionIndex One-based section index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return int The section post ID, or 0 when sections are not supported or creation failed.
	 */
	private function ensureSectionForCourse( int $courseId, int $sectionIndex, array $options ): int {
		$schema    = $this->plugin->getEntitySchema();
		$container = $schema->container;

		if ( null === $container ) {
			return 0;
		}

		$existing = (int) ( $options['section_id'] ?? 0 );

		if ( $existing > 0 ) {
			return $existing;
		}

		$cached = get_post_meta( $courseId, $container->cacheMetaKey, true );

		if ( is_array( $cached ) && isset( $cached[ $sectionIndex ] ) ) {
			$sectionId = (int) $cached[ $sectionIndex ];
			$this->recordSectionInIdMap( $sectionId, $sectionIndex, $options );

			return $sectionId;
		}

		$courseIndex = DeterministicTitle::courseIndex( $options );
		$prefix      = $this->getTitlePrefix( $container->entity );
		$title       = DeterministicTitle::section( $prefix, $courseIndex, $sectionIndex );
		$sectionId   = $this->insertPost(
			array(
				'post_title'   => $title,
				'post_type'    => $schema->getPostType( $container->entity ),
				'post_status'  => 'publish',
				'post_content' => DummyContent::section( $title, $sectionIndex ),
				'post_excerpt' => DummyContent::excerpt( 'section', $title, $sectionIndex ),
				'post_parent'  => $courseId,
			)
		);

		if ( $sectionId > 0 ) {
			update_post_meta( $sectionId, $container->parentMetaKey, $courseId );
			update_post_meta( $sectionId, '_llms_order', $sectionIndex );

			if ( ! is_array( $cached ) ) {
				$cached = array();
			}

			$cached[ $sectionIndex ] = $sectionId;
			update_post_meta( $courseId, $container->cacheMetaKey, $cached );

			$this->recordSectionInIdMap( $sectionId, $sectionIndex, $options );
		}

		return $sectionId;
	}

	/**
	 * Records a section as a quiz parent in the process ID map.
	 *
	 * @param int                  $sectionId Section post ID.
	 * @param int                  $sectionIndex One-based section index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	private function recordSectionInIdMap( int $sectionId, int $sectionIndex, array $options ): void {
		$processId = (string) ( $options['process_id'] ?? '' );

		if ( '' === $processId ) {
			return;
		}

		SeedingIdMap::recordQuizParent(
			$processId,
			'sections',
			(int) ( $options['course_index'] ?? 0 ),
			$sectionIndex,
			$sectionId,
			new ProcessRepository(),
		);
	}

	/**
	 * Finds the lesson in a section that a quiz should attach to.
	 *
	 * @param int $sectionId Section post ID.
	 * @param int $quizIndex One-based quiz index.
	 * @return int The lesson post ID, or 0 when the section has no lessons.
	 */
	private function resolveLessonForSectionQuiz( int $sectionId, int $quizIndex ): int {
		if ( $sectionId <= 0 ) {
			return 0;
		}

		$lessons = get_posts(
			array(
				'post_type'      => $this->getPostType( 'lessons' ),
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_key'       => '_llms_order',
				'meta_query'     => array(
					array(
						'key'   => '_llms_parent_section',
						'value' => $sectionId,
					),
				),
			)
		);

		if ( array() === $lessons ) {
			return 0;
		}

		$position = count( $lessons ) - max( 1, $quizIndex );

		$lesson = $lessons[ max( 0, $position ) ] ?? $lessons[ array_key_last( $lessons ) ];

		return (int) $lesson->ID;
	}

	/**
	 * Appends the pricing table and course syllabus blocks to a course.
	 *
	 * @param int $courseId Course post ID.
	 * @return void
	 */
	private function appendCourseBlocks( int $courseId ): void {
		$post = get_post( $courseId );

		if ( null === $post || 'course' !== $post->post_type ) {
			return;
		}

		wp_update_post(
			array(
				'ID'           => $courseId,
				'post_content' => rtrim( (string) $post->post_content ) . self::COURSE_BLOCKS,
			)
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
	 * Tells whether LifterLMS groups are available.
	 *
	 * @return bool True when LifterLMS can create groups.
	 */
	protected function hasGroupSupport(): bool {
		if ( function_exists( 'llms_create_group' ) ) {
			return true;
		}

		return function_exists( 'post_type_exists' ) && post_type_exists( 'llms_group' );
	}

	/**
	 * Seeds a LifterLMS group without enrolling the current admin, falling back to the parent seeder when groups are unsupported.
	 *
	 * @param int                  $count Number of groups to create.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> IDs of the created groups.
	 */
	public function seedGroups( int $count, array $options = array() ): array {
		if ( ! $this->hasGroupSupport() || ! class_exists( \LLMS_Group::class ) ) {
			return parent::seedGroups( $count, $options );
		}

		$index  = DeterministicTitle::resolveIndex( $options, 1 );
		$prefix = $options['title_prefix'] ?? $this->getTitlePrefix( 'groups' );
		$title  = DeterministicTitle::group( (string) $prefix, $index );
		$seats  = $this->resolveGroupSeatCount( $options );
		$meta   = array( '_llms_seats' => $seats );

		if ( function_exists( 'llms_groups' ) ) {
			$integration              = llms_groups()->get_integration();
			$meta['_llms_visibility'] = $integration->get_option( 'visibility', 'private' );
		}

		// Create without llms_create_group() so the current WP admin is not enrolled as primary admin.
		$group = new \LLMS_Group(
			'new',
			array(
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => DummyContent::group( $title, $index ),
				'post_excerpt' => DummyContent::excerpt( 'group', $title, $index ),
				'meta_input'   => $meta,
			)
		);

		$groupId = (int) $group->get( 'id' );

		if ( $groupId > 0 && (int) $group->get( 'seats' ) !== $seats ) {
			$group->set( 'seats', $seats );
		}

		return $groupId > 0 ? array( $groupId ) : array();
	}

	/**
	 * Calculates the seat count for a group from the total users and groups.
	 *
	 * @param array<string, mixed> $options Seeding options.
	 * @return int The number of seats, at least 2.
	 */
	private function resolveGroupSeatCount( array $options ): int {
		$totalUsers  = max( 0, (int) ( $options['total_users'] ?? 0 ) );
		$totalGroups = max( 1, (int) ( $options['groups'] ?? 1 ) );
		$perGroup    = (int) ceil( $totalUsers / $totalGroups );

		return max( 2, $perGroup + 2 );
	}

	/**
	 * Tracks a course on a group and links it as the group course when none is set.
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

		$this->trackGroupCourse( $groupId, $courseId );

		if ( function_exists( 'get_llms_group' ) ) {
			$group = get_llms_group( $groupId );

			if ( $group && ! $group->get( 'post_id' ) ) {
				$group->set( 'post_id', $courseId );
			}
		}

		// Course enrollment is intentionally left to the student journey.
	}

	/**
	 * Enrolls a user in a group as a member or primary admin and tracks the membership.
	 *
	 * @param int                  $userId User ID.
	 * @param int                  $groupId Group post ID.
	 * @param int                  $groupIndex One-based group index.
	 * @param bool                 $isGroupAdmin Whether the user is the group primary admin.
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

		$trigger = 'populater_group_' . $groupId;
		$role    = $isGroupAdmin ? 'primary_admin' : 'member';

		if ( class_exists( \LLMS_Groups_Enrollment::class ) ) {
			\LLMS_Groups_Enrollment::add( $userId, $groupId, $trigger, $role );
		} elseif ( function_exists( 'llms_enroll_student' ) ) {
			llms_enroll_student( $userId, $groupId, $trigger );
		}

		$this->trackGroupMember( $groupId, $userId );
	}

	/**
	 * Stores a course ID in the group course tracking meta.
	 *
	 * @param int $groupId Group post ID.
	 * @param int $courseId Course post ID.
	 * @return void
	 */
	private function trackGroupCourse( int $groupId, int $courseId ): void {
		$courses = get_post_meta( $groupId, '_populater_group_courses', true );

		if ( ! is_array( $courses ) ) {
			$courses = array();
		}

		$courses[] = $courseId;
		update_post_meta( $groupId, '_populater_group_courses', array_values( array_unique( array_map( 'intval', $courses ) ) ) );
	}

	/**
	 * Stores a user ID in the group member tracking meta.
	 *
	 * @param int $groupId Group post ID.
	 * @param int $userId User ID.
	 * @return void
	 */
	private function trackGroupMember( int $groupId, int $userId ): void {
		$members = get_post_meta( $groupId, '_populater_group_member_ids', true );

		if ( ! is_array( $members ) ) {
			$members = array();
		}

		$members[] = $userId;
		update_post_meta( $groupId, '_populater_group_member_ids', array_values( array_unique( array_map( 'intval', $members ) ) ) );
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

		$completed = LifterLMSStudentActivity::completeAllCourses( $user->ID );

		return array() !== $completed ? array( $user->ID ) : array();
	}

	/**
	 * Pre-fills the LifterLMS profile for a new student.
	 *
	 * @param int                  $userId User ID.
	 * @param int                  $index One-based user index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterUserCreated( int $userId, int $index, array $options = array() ): void {
		LifterLmsStudentProfile::populate( $userId, $index, 'student' );
	}

	/**
	 * Pre-fills the LifterLMS profile for a new group admin.
	 *
	 * @param int                  $userId User ID.
	 * @param int                  $index One-based user index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterGroupAdminCreated( int $userId, int $index, array $options = array() ): void {
		LifterLmsStudentProfile::populate( $userId, $index, 'groupadmin' );
	}
}
