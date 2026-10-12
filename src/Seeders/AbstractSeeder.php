<?php
/**
 * Base contract for all LMS seeders.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeders;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Seeding\SeedConfig;
use Tangible\Populater\Seeding\SeedQueueItem;
use Tangible\Populater\Support\DeterministicTitle;
use Tangible\Populater\Support\DummyContent;
use Tangible\Populater\Support\GroupIndexResolver;
use Tangible\Populater\Support\SeededUsername;
use Tangible\Populater\Support\SeededUserProfile;

/**
 * Base contract for all LMS seeders.
 *
 * Default {@see seedCourses()}, {@see seedLessons()}, etc. assume WordPress posts
 * and post meta. LMS plugins that use custom tables, wp_options, or vendor APIs
 * should override the relevant methods and keep the shared queue via
 * {@see buildSeedQueue()}.
 *
 * @see docs/LMS-EXTENSION.md
 */
abstract class AbstractSeeder {

	public const USER_META_MARKER   = '_tangible_populater_user';
	public const COURSE_META_MARKER = '_tangible_populater_course';

	/**
	 * Constructor.
	 *
	 * @param AbstractLmsPlugin $plugin LMS plugin this seeder belongs to.
	 */
	public function __construct(
		protected readonly AbstractLmsPlugin $plugin,
	) {}

	/**
	 * Get the LMS plugin name.
	 *
	 * @return string Plugin name.
	 */
	public function getName(): string {
		return $this->plugin->getName();
	}

	/**
	 * Get the LMS plugin slug.
	 *
	 * @return string Plugin slug.
	 */
	public function getSlug(): string {
		return $this->plugin->getSlug();
	}

	/**
	 * Whether the LMS plugin is active.
	 *
	 * @return bool True when the plugin is active.
	 */
	public function isActive(): bool {
		return $this->plugin->isActive();
	}

	/**
	 * Get the post type for an entity key such as courses, lessons, quizzes or certificates.
	 *
	 * @param string $entity Entity key.
	 * @return string Post type slug.
	 */
	protected function getPostType( string $entity ): string {
		return $this->plugin->getEntitySchema()->getPostType( $entity );
	}

	/**
	 * Resolve the meta values to set after a post is created.
	 *
	 * @param string               $entity  Entity key.
	 * @param array<string, mixed> $context Context, e.g. courseId, lessonId, index.
	 * @return array<string, mixed> Meta values keyed by meta key.
	 */
	protected function getMetaFor( string $entity, array $context ): array {
		return $this->plugin->getEntitySchema()->resolveMeta( $entity, $context );
	}

	/**
	 * Get the title prefix for generated content of an entity.
	 *
	 * @param string $entity Entity key.
	 * @return string Title prefix.
	 */
	protected function getTitlePrefix( string $entity ): string {
		return $this->plugin->getEntitySchema()->getTitlePrefix( $entity, $this->getName() );
	}

	/**
	 * Create courses as WordPress posts and apply course meta.
	 *
	 * @param int                  $count   Number of courses.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created course IDs.
	 */
	public function seedCourses( int $count, array $options = array() ): array {
		$ids        = array();
		$prefix     = $options['title_prefix'] ?? $this->getTitlePrefix( 'courses' );
		$startIndex = isset( $options['index'] ) ? (int) $options['index'] : 1;

		for ( $offset = 0; $offset < $count; $offset++ ) {
			$index  = $startIndex + $offset;
			$title  = DeterministicTitle::course( (string) $prefix, $index );
			$postId = $this->insertPost(
				array(
					'post_title'   => $title,
					'post_type'    => $this->getPostType( 'courses' ),
					'post_status'  => 'publish',
					'post_content' => DummyContent::course( $title, $index ),
					'post_excerpt' => DummyContent::excerpt( 'course', $title, $index ),
				)
			);

			if ( $postId > 0 ) {
				update_post_meta( $postId, self::COURSE_META_MARKER, $this->getSlug() );
				$this->afterCourseCreated( $postId, $index, $options );
				$this->maybeAssignCourseToGroup( $postId, $options );
				$ids[] = $postId;
			}
		}

		return $ids;
	}

	/**
	 * Embeds a machine-readable structure comment into the course post_content so that
	 * k6 stress tests can parse lesson/topic/quiz counts from the course page without
	 * making additional discovery requests.
	 *
	 * @param array<string, mixed> $data Queue item data.
	 * @return list<int> IDs of courses whose structure was embedded.
	 */
	public function seedCourseStructure( array $data ): array {
		$courseId = (int) ( $data['course_id'] ?? 0 );

		if ( $courseId <= 0 ) {
			return array();
		}

		$structure = array(
			'lessons'             => (int) ( $data['lessons_per_course'] ?? 0 ),
			'topics_per_lesson'   => (int) ( $data['topics_per_lesson'] ?? 0 ),
			'quizzes_per_lesson'  => (int) ( $data['quizzes_per_lesson'] ?? 0 ),
			'sections_per_course' => (int) ( $data['sections_per_course'] ?? 0 ),
			'lessons_per_section' => (int) ( $data['lessons_per_section'] ?? 0 ),
		);

		$structure = array_filter( $structure, static fn( int $v ): bool => $v > 0 );

		$post = function_exists( 'get_post' ) ? get_post( $courseId ) : null;

		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		$json          = wp_json_encode(
			$structure,
			JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
		);
		$comment       = '<!-- populater:structure ' . $json . ' -->';
		$script        = '<script type="application/json" id="populater-structure">' . $json . '</script>';
		$excerptMarker = '[populater:structure ' . $json . ']';
		$content       = (string) preg_replace( '/<!--\s*populater:structure\s+[^>]*-->/', '', $post->post_content );
		$content       = (string) preg_replace( '/<script[^>]+id=["\']populater-structure["\'][^>]*>.*?<\/script>/s', '', $content );
		$excerpt       = (string) preg_replace( '/\[populater:structure\s+\{[^]]*}]/', '', $post->post_excerpt );

		wp_update_post(
			array(
				'ID'           => $courseId,
				'post_content' => trim( $content ) . "\n" . $comment . "\n" . $script,
				'post_excerpt' => trim( $excerpt ) . "\n" . $excerptMarker,
			)
		);

		return array();
	}

	/**
	 * Create lessons under a course as WordPress posts.
	 *
	 * @param int                  $count    Number of lessons.
	 * @param int                  $courseId Parent course ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created lesson IDs.
	 */
	public function seedLessons( int $count, int $courseId, array $options = array() ): array {
		$ids         = array();
		$prefix      = $options['title_prefix'] ?? $this->getTitlePrefix( 'lessons' );
		$courseIndex = DeterministicTitle::courseIndex( $options );

		for ( $i = 1; $i <= $count; $i++ ) {
			$index  = DeterministicTitle::resolveIndex( $options, $i );
			$title  = DeterministicTitle::lesson( (string) $prefix, $courseIndex, $index );
			$postId = $this->insertPost(
				array(
					'post_title'   => $title,
					'post_type'    => $this->getPostType( 'lessons' ),
					'post_status'  => 'publish',
					'post_content' => DummyContent::lesson( $title, $index ),
					'post_excerpt' => DummyContent::excerpt( 'lesson', $title, $index ),
					'post_parent'  => $courseId,
				)
			);

			if ( $postId > 0 ) {
				$this->applyMeta( $postId, $this->getMetaFor( 'lessons', array( 'courseId' => $courseId ) ) );
				$this->afterLessonCreated( $postId, $courseId, $index, $options );
				$ids[] = $postId;
			}
		}

		return $ids;
	}

	/**
	 * Create quizzes under a parent post as WordPress posts.
	 *
	 * @param int                  $count    Number of quizzes.
	 * @param int                  $parentId Parent post ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created quiz IDs.
	 */
	public function seedQuizzes( int $count, int $parentId, array $options = array() ): array {
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
				$this->applyMeta( $postId, $this->getMetaFor( 'quizzes', array( 'parentId' => $parentId ) ) );
				$this->afterQuizCreated( $postId, $parentId, $index, $options );
				$this->seedQuestionsForQuiz( $postId, $options );
				$ids[] = $postId;
			}
		}

		return $ids;
	}

	/**
	 * Create questions under a quiz as WordPress posts.
	 *
	 * @param int                  $count  Number of questions.
	 * @param int                  $quizId Parent quiz ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created question IDs.
	 */
	public function seedQuestions( int $count, int $quizId, array $options = array() ): array {
		$ids         = array();
		$prefix      = $options['title_prefix'] ?? $this->getTitlePrefix( 'questions' );
		$courseIndex = DeterministicTitle::courseIndex( $options );
		$startIndex  = isset( $options['index'] ) ? (int) $options['index'] : 1;

		for ( $offset = 0; $offset < $count; $offset++ ) {
			$index  = $startIndex + $offset;
			$title  = DeterministicTitle::question( (string) $prefix, $courseIndex, $options, $index );
			$postId = $this->insertPost(
				array(
					'post_title'   => $title,
					'post_type'    => $this->getPostType( 'questions' ),
					'post_status'  => 'publish',
					'post_content' => DummyContent::question( $title, $index ),
				)
			);

			if ( $postId > 0 ) {
				$this->applyMeta( $postId, $this->getMetaFor( 'questions', array( 'quizId' => $quizId ) ) );
				$this->afterQuestionCreated( $postId, $quizId, $index, $options );
				$ids[] = $postId;
			}
		}

		return $ids;
	}

	/**
	 * Create student users, or a single student when an index is given.
	 *
	 * @param int                  $count   Number of users.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created user IDs.
	 */
	public function seedUsers( int $count, array $options = array() ): array {
		if ( array_key_exists( 'index', $options ) ) {
			return $this->createSeededUserAtIndex( (int) $options['index'], $options );
		}

		$ids = array();

		for ( $i = 1; $i <= $count; $i++ ) {
			$ids = array_merge( $ids, $this->createSeededUserAtIndex( $i, $options ) );
		}

		return $ids;
	}

	/**
	 * Create groups when the LMS supports them.
	 *
	 * @param int                  $count   Number of groups.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created group IDs.
	 */
	public function seedGroups( int $count, array $options = array() ): array {
		if ( ! $this->hasGroupSupport() ) {
			return array();
		}

		$ids    = array();
		$index  = DeterministicTitle::resolveIndex( $options, 1 );
		$prefix = $options['title_prefix'] ?? $this->getTitlePrefix( 'groups' );
		$title  = DeterministicTitle::group( (string) $prefix, $index );
		$postId = $this->insertPost(
			array(
				'post_title'   => $title,
				'post_type'    => $this->getPostType( 'groups' ),
				'post_status'  => 'publish',
				'post_content' => DummyContent::group( $title, $index ),
				'post_excerpt' => DummyContent::excerpt( 'group', $title, $index ),
			)
		);

		if ( $postId > 0 ) {
			$this->afterGroupCreated( $postId, $index, $options );
			$ids[] = $postId;
		}

		return $ids;
	}

	/**
	 * Create a group admin user and assign it to a group when group data is present.
	 *
	 * @param int                  $count   Number of group admins.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created group admin user IDs.
	 */
	public function seedGroupAdmins( int $count, array $options = array() ): array {
		$index = (int) ( $options['index'] ?? 1 );

		$userId = $this->createWpUser(
			array(
				'role_type' => 'groupadmin',
				'index'     => $index,
				'password'  => $options['user_password'] ?? null,
			)
		);

		if ( $userId <= 0 ) {
			return array();
		}

		$this->afterGroupAdminCreated( $userId, $index, $options );
		$this->maybeAssignUserToGroup( $userId, $options, true );

		return array( $userId );
	}

	/**
	 * Create certificate posts.
	 *
	 * @param int                  $count   Number of certificates.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created certificate IDs.
	 */
	public function seedCertificates( int $count, array $options = array() ): array {
		$ids        = array();
		$prefix     = $options['title_prefix'] ?? $this->getTitlePrefix( 'certificates' );
		$startIndex = isset( $options['index'] ) ? (int) $options['index'] : 1;

		for ( $offset = 0; $offset < $count; $offset++ ) {
			$index  = $startIndex + $offset;
			$title  = DeterministicTitle::certificate( (string) $prefix, $index );
			$postId = $this->insertPost(
				array(
					'post_title'   => $title,
					'post_type'    => $this->getPostType( 'certificates' ),
					'post_status'  => 'publish',
					'post_content' => sprintf( 'Sample certificate %d.', $index ),
				)
			);

			if ( $postId > 0 ) {
				$ids[] = $postId;
			}
		}

		return $ids;
	}

	/**
	 * Simulates a student completing all seeded courses: enroll, finish lessons,
	 * record quiz attempts, and mark courses complete.
	 *
	 * Subclasses for supported LMS plugins (LifterLMS, LearnDash) override this.
	 *
	 * @param int                  $count   Number of students.
	 * @param array<string, mixed> $options Keys: index (student number).
	 * @return list<int> User IDs processed.
	 */
	public function seedStudentActivity( int $count, array $options = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable default.
		return array();
	}

	/**
	 * List the entity types this seeder can seed.
	 *
	 * @return list<string> Entity type keys.
	 */
	public function getSeedableTypes(): array {
		$types = array( 'courses', 'lessons', 'quizzes', 'users', 'certificates' );

		if ( $this->hasGroupSupport() ) {
			$types[] = 'groups';
		}

		$types[] = 'group_admins';

		return $types;
	}

	/**
	 * Builds the ordered seed queue for background processing.
	 *
	 * The queue always runs in three distinct phases:
	 *   Phase 1 — Structure: groups, courses, lessons, quizzes, course_structure
	 *   Phase 2 — Users:     students and group admins
	 *   Phase 3 — Activity:  student_activity items (only when completeCourses = true)
	 *
	 * student_activity items are appended AFTER every course and user item so that
	 * by the time a completion step runs, every course and every WP user already
	 * exists in the database.
	 *
	 * @param array<string, mixed>|SeedConfig $config Seed configuration.
	 * @return list<SeedQueueItem> Ordered queue items.
	 */
	public function buildSeedQueue( array|SeedConfig $config ): array {
		$seedConfig = $config instanceof SeedConfig ? $config : SeedConfig::fromArray( $config );
		$queue      = array();
		$shared     = $this->sharedQueueData( $seedConfig );

		if ( $seedConfig->groups > 0 ) {
			if ( $this->hasGroupSupport() ) {
				for ( $g = 1; $g <= $seedConfig->groups; $g++ ) {
					$queue[] = new SeedQueueItem( 'group', array_merge( $shared, array( 'index' => $g ) ) );
				}
			}

			for ( $g = 1; $g <= $seedConfig->groups; $g++ ) {
				$queue[] = new SeedQueueItem( 'group_admin', array_merge( $shared, array( 'index' => $g ) ) );
			}
		}

		for ( $c = 1; $c <= $seedConfig->courses; $c++ ) {
			$courseData = array_merge( $shared, array( 'index' => $c ) );

			if ( $seedConfig->groups > 0 ) {
				$courseData['group_index'] = GroupIndexResolver::resolve(
					$c,
					$seedConfig->courses,
					$seedConfig->groups,
				);
			}

			$queue[] = new SeedQueueItem( 'course', $courseData );

			for ( $l = 1; $l <= $seedConfig->lessonsPerCourse; $l++ ) {
				$queue[] = new SeedQueueItem(
					'lesson',
					array(
						'course_index'        => $c,
						'index'               => $l,
						'lessons_per_course'  => $seedConfig->lessonsPerCourse,
						'lessons_per_section' => $seedConfig->lessonsPerSection,
						'topics_per_lesson'   => $seedConfig->topicsPerLesson,
						'sections_per_course' => $seedConfig->sectionsPerCourse,
						'modules_per_course'  => $seedConfig->modulesPerCourse,
						'group_index'         => $courseData['group_index'] ?? 0,
					)
				);
			}

			foreach ( $this->buildQuizQueueItems( $c, $seedConfig ) as $item ) {
				$queue[] = $item;
			}

			$queue[] = new SeedQueueItem(
				'course_structure',
				array(
					'course_index'        => $c,
					'lessons_per_course'  => $seedConfig->lessonsPerCourse,
					'topics_per_lesson'   => $seedConfig->topicsPerLesson,
					'quizzes_per_lesson'  => $seedConfig->quizzesPerSection,
					'sections_per_course' => $seedConfig->sectionsPerCourse,
					'lessons_per_section' => $seedConfig->lessonsPerSection,
				)
			);
		}//end for

		for ( $u = 1; $u <= $seedConfig->users; $u++ ) {
			$userData = array_merge( $shared, array( 'index' => $u ) );

			if ( $seedConfig->groups > 0 ) {
				$userData['group_index'] = GroupIndexResolver::resolve(
					$u,
					$seedConfig->users,
					$seedConfig->groups,
				);
			}

			$queue[] = new SeedQueueItem( 'user', $userData );
		}

		// Phase 3 — Activity: runs after ALL course and user items are queued.
		// Each student_activity item queries seeded courses at run-time (not at
		// queue-build time), so by the time it executes the courses are in the DB.
		if ( $seedConfig->completeCourses && $seedConfig->users > 0 && $seedConfig->courses > 0 ) {
			for ( $u = 1; $u <= $seedConfig->users; $u++ ) {
				$queue[] = new SeedQueueItem( 'student_activity', array_merge( $shared, array( 'index' => $u ) ) );
			}
		}

		return $queue;
	}

	// -------------------------------------------------------------------------
	// Protected helpers
	// -------------------------------------------------------------------------

	/**
	 * Insert a post with a deterministic slug, returning 0 on failure.
	 *
	 * @param array<string, mixed> $args Arguments for wp_insert_post().
	 * @return int New post ID, or 0 on failure.
	 */
	protected function insertPost( array $args ): int {
		if ( ! isset( $args['post_name'] ) && isset( $args['post_title'] ) && is_string( $args['post_title'] ) ) {
			$args['post_name'] = DeterministicTitle::slug( $args['post_title'] );
		}

		$postId = wp_insert_post( $args );

		if ( is_wp_error( $postId ) || ! is_int( $postId ) ) {
			return 0;
		}

		return $postId;
	}

	/**
	 * Create a student user at a specific index.
	 *
	 * @param int                  $index   Student index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created user IDs.
	 */
	protected function createSeededUserAtIndex( int $index, array $options ): array {
		$userId = $this->createWpUser(
			array(
				'role_type' => 'student',
				'index'     => $index,
				'password'  => $options['user_password'] ?? null,
			)
		);

		if ( $userId <= 0 ) {
			return array();
		}

		$this->afterUserCreated( $userId, $index, $options );
		$this->maybeAssignUserToGroup( $userId, $options, false );

		return array( $userId );
	}

	/**
	 * Create a WordPress user.
	 *
	 * @param array<string, mixed> $options Keys: role_type, index, password.
	 * @return int Created user ID, or 0 on failure.
	 */
	protected function createWpUser( array $options ): int {
		$index    = (int) ( $options['index'] ?? 1 );
		$roleType = (string) ( $options['role_type'] ?? 'student' );
		$username = SeededUsername::username( $this->getSlug(), $roleType, $index );
		$email    = SeededUsername::email( $this->getSlug(), $roleType, $index );
		$password = is_string( $options['password'] ?? null ) && '' !== $options['password']
			? (string) $options['password']
			: SeedConfig::DEFAULT_USER_PASSWORD;

		if ( function_exists( 'username_exists' ) && username_exists( $username ) ) {
			$username .= '_' . wp_generate_password( 4, false, false );
		}

		$userId = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $userId ) || ! is_int( $userId ) ) {
			return 0;
		}

		if ( function_exists( 'update_user_meta' ) ) {
			update_user_meta( $userId, self::USER_META_MARKER, 1 );
		}

		$user = function_exists( 'get_user_by' ) ? get_user_by( 'id', $userId ) : false;
		if ( $user instanceof \WP_User && function_exists( 'get_role' ) && get_role( 'subscriber' ) !== null ) {
			$user->set_role( 'subscriber' );
		}

		return $userId;
	}

	/**
	 * Whether the LMS plugin registers a groups post type that exists.
	 *
	 * @return bool True when groups are supported.
	 */
	protected function hasGroupSupport(): bool {
		$schema = $this->plugin->getEntitySchema();

		if ( ! isset( $schema->postTypes['groups'] ) ) {
			return false;
		}

		if ( ! function_exists( 'post_type_exists' ) ) {
			return true;
		}

		return post_type_exists( $schema->getPostType( 'groups' ) );
	}

	/**
	 * Build the data merged into every seed queue item.
	 *
	 * @param SeedConfig $seedConfig Seed configuration.
	 * @return array<string, mixed> Shared queue item data.
	 */
	protected function sharedQueueData( SeedConfig $seedConfig ): array {
		$data = array(
			'groups'        => $seedConfig->groups,
			'total_courses' => $seedConfig->courses,
			'total_users'   => $seedConfig->users,
		);

		if ( null !== $seedConfig->userPassword ) {
			$data['user_password'] = $seedConfig->userPassword;
		}

		return $data;
	}

	/**
	 * Assign a course to a group when group data is present.
	 *
	 * @param int                  $courseId Course ID.
	 * @param array<string, mixed> $options  Seeding options.
	 * @return void
	 */
	protected function maybeAssignCourseToGroup( int $courseId, array $options ): void {
		$groupId    = (int) ( $options['group_id'] ?? 0 );
		$groupIndex = (int) ( $options['group_index'] ?? 0 );

		if ( $groupId <= 0 && $groupIndex <= 0 ) {
			return;
		}

		$this->assignCourseToGroup( $courseId, $groupId, $groupIndex, $options );
	}

	/**
	 * Assign a user to a group when group data is present.
	 *
	 * @param int                  $userId       User ID.
	 * @param array<string, mixed> $options      Seeding options.
	 * @param bool                 $isGroupAdmin Whether the user is a group admin.
	 * @return void
	 */
	protected function maybeAssignUserToGroup( int $userId, array $options, bool $isGroupAdmin ): void {
		$groupId    = (int) ( $options['group_id'] ?? 0 );
		$groupIndex = (int) ( $options['group_index'] ?? 0 );

		if ( $groupId <= 0 && $groupIndex <= 0 ) {
			return;
		}

		$this->assignUserToGroup( $userId, $groupId, $groupIndex, $isGroupAdmin, $options );
	}

	/**
	 * Assign a course to a group (override in subclasses).
	 *
	 * @param int                  $courseId   Course ID.
	 * @param int                  $groupId    Group ID.
	 * @param int                  $groupIndex Group index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function assignCourseToGroup(
		int $courseId,
		int $groupId,
		int $groupIndex,
		array $options = array(),
	): void {}

	/**
	 * Assign a user to a group (override in subclasses).
	 *
	 * @param int                  $userId       User ID.
	 * @param int                  $groupId      Group ID.
	 * @param int                  $groupIndex   Group index.
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
	): void {}

	/**
	 * Build a default title from a prefix and an index.
	 *
	 * @deprecated Use {@see DeterministicTitle} helpers instead.
	 *
	 * @param string $prefix Title prefix.
	 * @param int    $index  Index.
	 * @return string Generated title.
	 */
	protected function defaultTitle( string $prefix, int $index ): string {
		return "$prefix $index";
	}

	/**
	 * Apply non-empty meta values to a post.
	 *
	 * @param int                  $postId Post ID.
	 * @param array<string, mixed> $meta   Meta values keyed by meta key.
	 * @return void
	 */
	protected function applyMeta( int $postId, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			if ( 0 !== $value && '' !== $value ) {
				update_post_meta( $postId, (string) $key, $value );
			}
		}
	}

	/**
	 * Hook run after a course is created.
	 *
	 * @param int                  $postId  Post ID.
	 * @param int                  $index   Course index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterCourseCreated( int $postId, int $index, array $options = array() ): void {}

	/**
	 * Hook run after a student is created; populates the user profile by default.
	 *
	 * @param int                  $userId  User ID.
	 * @param int                  $index   Student index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterUserCreated( int $userId, int $index, array $options = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable hook signature.
		SeededUserProfile::populate( $userId, $index, 'student' );
	}

	/**
	 * Hook run after a group is created.
	 *
	 * @param int                  $postId  Post ID.
	 * @param int                  $index   Group index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterGroupCreated( int $postId, int $index, array $options = array() ): void {}

	/**
	 * Hook run after a group admin is created; populates the user profile by default.
	 *
	 * @param int                  $userId  User ID.
	 * @param int                  $index   Group admin index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterGroupAdminCreated( int $userId, int $index, array $options = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Overridable hook signature.
		SeededUserProfile::populate( $userId, $index, 'groupadmin' );
	}

	/**
	 * Hook run after a lesson is created.
	 *
	 * @param int                  $postId   Post ID.
	 * @param int                  $courseId Course ID.
	 * @param int                  $index    Lesson index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterLessonCreated( int $postId, int $courseId, int $index, array $options = array() ): void {}

	/**
	 * Hook run after a quiz is created.
	 *
	 * @param int                  $postId   Post ID.
	 * @param int                  $parentId Parent post ID.
	 * @param int                  $index    Quiz index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterQuizCreated( int $postId, int $parentId, int $index, array $options = array() ): void {}

	/**
	 * Build quiz queue items for a course.
	 *
	 * @param int        $courseIndex Course index.
	 * @param SeedConfig $seedConfig  Seed configuration.
	 * @return list<SeedQueueItem> Quiz queue items.
	 */
	protected function buildQuizQueueItems( int $courseIndex, SeedConfig $seedConfig ): array {
		$quizParent = $this->plugin->getEntitySchema()->quizParentEntity;
		$items      = array();
		$base       = array(
			'course_index'       => $courseIndex,
			'questions_per_quiz' => $seedConfig->questionsPerQuiz,
			'quiz_parent_entity' => $quizParent,
		);

		if ( 'topics' === $quizParent ) {
			for ( $l = 1; $l <= $seedConfig->lessonsPerCourse; $l++ ) {
				for ( $t = 1; $t <= $seedConfig->topicsPerLesson; $t++ ) {
					for ( $q = 1; $q <= $seedConfig->quizzesPerSection; $q++ ) {
						$items[] = new SeedQueueItem(
							'quiz',
							array_merge(
								$base,
								array(
									'lesson_index'      => $l,
									'quiz_parent_index' => $t,
									'index'             => $q,
								)
							)
						);
					}
				}
			}

			return $items;
		}//end if

		if ( 'sections' === $quizParent ) {
			for ( $s = 1; $s <= $seedConfig->sectionsPerCourse; $s++ ) {
				for ( $q = 1; $q <= $seedConfig->quizzesPerSection; $q++ ) {
					$items[] = new SeedQueueItem(
						'quiz',
						array_merge(
							$base,
							array(
								'quiz_parent_index' => $s,
								'index'             => $q,
							)
						)
					);
				}
			}

			return $items;
		}

		if ( 'modules' === $quizParent ) {
			for ( $m = 1; $m <= $seedConfig->modulesPerCourse; $m++ ) {
				for ( $q = 1; $q <= $seedConfig->quizzesPerSection; $q++ ) {
					$items[] = new SeedQueueItem(
						'quiz',
						array_merge(
							$base,
							array(
								'quiz_parent_index' => $m,
								'index'             => $q,
							)
						)
					);
				}
			}

			return $items;
		}

		for ( $l = 1; $l <= $seedConfig->lessonsPerCourse; $l++ ) {
			for ( $q = 1; $q <= $seedConfig->quizzesPerSection; $q++ ) {
				$items[] = new SeedQueueItem(
					'quiz',
					array_merge(
						$base,
						array(
							'lesson_index' => $l,
							'index'        => $q,
						)
					)
				);
			}
		}

		return $items;
	}

	/**
	 * Hook run after a question is created.
	 *
	 * @param int                  $postId  Post ID.
	 * @param int                  $quizId  Quiz ID.
	 * @param int                  $index   Question index.
	 * @param array<string, mixed> $options Seeding options.
	 * @return void
	 */
	protected function afterQuestionCreated( int $postId, int $quizId, int $index, array $options = array() ): void {}

	/**
	 * Seed the configured number of questions for a quiz.
	 *
	 * @param int                  $quizId  Quiz ID.
	 * @param array<string, mixed> $options Seeding options.
	 * @return list<int> Created question IDs.
	 */
	protected function seedQuestionsForQuiz( int $quizId, array $options = array() ): array {
		$count = (int) ( $options['questions_per_quiz'] ?? 0 );

		if ( $count <= 0 || $quizId <= 0 ) {
			return array();
		}

		return $this->seedQuestions( $count, $quizId, $options );
	}
}
