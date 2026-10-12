<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeding;

use Tangible\Populater\Seeding\ProcessRepository;
use Tangible\Populater\Seeding\SeedingIdMap;
use Brain\Monkey\Functions;

class SeedingIdMapTest extends \WPTestCase {

	private ProcessRepository $repository;

	protected function setUp(): void {
		parent::setUp();
		$this->repository = new ProcessRepository();
		Functions\when( 'get_option' )->justReturn( null );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
	}

	public function test_enrich_resolves_lesson_course_id(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'courses' => array( 1 => 42 ),
				'lessons' => array(),
			)
		);

		$data = SeedingIdMap::enrich(
			'proc-1',
			'lesson',
			array(
				'course_index' => 1,
				'index'        => 1,
			),
			$this->repository
		);

		$this->assertSame( 42, $data['course_id'] );
	}

	public function test_enrich_resolves_quiz_topic_id(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'courses' => array( 2 => 55 ),
				'lessons' => array( 2 => array( 3 => 99 ) ),
				'topics'  => array( 2 => array( 3 => array( 1 => 777 ) ) ),
			)
		);

		$data = SeedingIdMap::enrich(
			'proc-1',
			'quiz',
			array(
				'course_index'       => 2,
				'lesson_index'       => 3,
				'quiz_parent_index'  => 1,
				'quiz_parent_entity' => 'topics',
				'index'              => 1,
			),
			$this->repository
		);

		$this->assertSame( 777, $data['quiz_parent_id'] );
		$this->assertSame( 99, $data['lesson_id'] );
		$this->assertSame( 55, $data['course_id'] );
	}

	public function test_enrich_resolves_quiz_lesson_topic_id(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'courses'            => array( 1 => 10 ),
				'lessons'            => array( 1 => array( 2 => 88 ) ),
				'lesson_quiz_topics' => array( 1 => array( 2 => 501 ) ),
			)
		);

		$data = SeedingIdMap::enrich(
			'proc-1',
			'quiz',
			array(
				'course_index'       => 1,
				'lesson_index'       => 2,
				'quiz_parent_entity' => 'lessons',
				'index'              => 1,
			),
			$this->repository
		);

		$this->assertSame( 88, $data['quiz_parent_id'] );
		$this->assertSame( 88, $data['lesson_id'] );
		$this->assertSame( 501, $data['topic_id'] );
	}

	public function test_enrich_resolves_quiz_section_lesson_id(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'courses'             => array( 1 => 10 ),
				'sections'            => array( 1 => array( 1 => 88 ) ),
				'section_lessons'     => array( 1 => array( 1 => array( 11, 12, 13 ) ) ),
				'section_quiz_topics' => array( 1 => array( 1 => 501 ) ),
			)
		);

		$data = SeedingIdMap::enrich(
			'proc-1',
			'quiz',
			array(
				'course_index'       => 1,
				'quiz_parent_index'  => 1,
				'quiz_parent_entity' => 'sections',
				'index'              => 1,
			),
			$this->repository
		);

		$this->assertSame( 88, $data['quiz_parent_id'] );
		$this->assertSame( 13, $data['lesson_id'] );
		$this->assertSame( 501, $data['topic_id'] );
	}

	public function test_record_then_enrich_lesson_uses_stored_course(): void {
		$stored = array(
			'courses' => array(),
			'lessons' => array(),
		);

		Functions\when( 'get_option' )->alias(
			static function ( string $key ) use ( &$stored ) {
				return 'tangible_populater_ids_proc-1' === $key ? $stored : null;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$stored ): bool {
				if ( 'tangible_populater_ids_proc-1' === $key && is_array( $value ) ) {
					$stored = $value;
				}

				return true;
			}
		);

		SeedingIdMap::record( 'proc-1', 'course', array( 'index' => 1 ), array( 10 ), $this->repository );

		$data = SeedingIdMap::enrich(
			'proc-1',
			'lesson',
			array(
				'course_index' => 1,
				'index'        => 1,
			),
			$this->repository
		);

		$this->assertSame( 10, $data['course_id'] );
	}

	public function test_enrich_resolves_group_id_for_course_and_user(): void {
		$stored = array(
			'groups' => array(
				1 => 501,
				2 => 502,
			),
		);

		Functions\when( 'get_option' )->alias(
			static function ( string $key ) use ( &$stored ) {
				return 'tangible_populater_ids_proc-1' === $key ? $stored : null;
			}
		);

		$course = SeedingIdMap::enrich(
			'proc-1',
			'course',
			array(
				'index'       => 3,
				'group_index' => 2,
			),
			$this->repository
		);

		$user = SeedingIdMap::enrich(
			'proc-1',
			'user',
			array(
				'index'       => 7,
				'group_index' => 1,
			),
			$this->repository
		);

		$admin = SeedingIdMap::enrich( 'proc-1', 'group_admin', array( 'index' => 2 ), $this->repository );

		$this->assertSame( 502, $course['group_id'] );
		$this->assertSame( 501, $user['group_id'] );
		$this->assertSame( 502, $admin['group_id'] );
		$this->assertSame( 2, $admin['group_index'] );
	}
}
