<?php
/**
 * Seeding ID map.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Tracks created post IDs per seeding process so later queue items can resolve
 * course_index / lesson_index / section or topic indices into real IDs.
 */
final class SeedingIdMap {

	/**
	 * Resolves course, lesson, group and quiz parent indices in a queue item payload into real post IDs.
	 *
	 * @param string               $processId  ID of the seeding process.
	 * @param string               $type       Queue item type.
	 * @param array<string, mixed> $data       Queue item payload containing indices.
	 * @param ProcessRepository    $repository Repository holding the process ID map.
	 * @return array<string, mixed> Payload extended with the resolved IDs.
	 */
	public static function enrich(
		string $processId,
		string $type,
		array $data,
		ProcessRepository $repository,
	): array {
		$map = $repository->getIdMap( $processId );

		if ( 'lesson' === $type || 'course_structure' === $type ) {
			$courseIndex       = (int) ( $data['course_index'] ?? 0 );
			$data['course_id'] = (int) ( $map['courses'][ $courseIndex ] ?? 0 );
		}

		if ( in_array( $type, array( 'course', 'user', 'group_admin' ), true ) ) {
			$groupIndex = (int) ( $data['group_index'] ?? ( 'group_admin' === $type ? ( $data['index'] ?? 0 ) : 0 ) );

			if ( $groupIndex > 0 ) {
				$data['group_index'] = $groupIndex;
				$data['group_id']    = (int) ( $map['groups'][ $groupIndex ] ?? 0 );
			}
		}

		if ( 'quiz' === $type ) {
			$courseIndex       = (int) ( $data['course_index'] ?? 0 );
			$data['course_id'] = (int) ( $map['courses'][ $courseIndex ] ?? 0 );
			$parentEntity      = (string) ( $data['quiz_parent_entity'] ?? '' );
			$parentIndex       = (int) ( $data['quiz_parent_index'] ?? 0 );

			if ( 'topics' === $parentEntity && $parentIndex > 0 ) {
				$lessonIndex            = (int) ( $data['lesson_index'] ?? 0 );
				$data['lesson_id']      = (int) ( $map['lessons'][ $courseIndex ][ $lessonIndex ] ?? 0 );
				$data['quiz_parent_id'] = (int) ( $map['topics'][ $courseIndex ][ $lessonIndex ][ $parentIndex ] ?? 0 );
			} elseif ( 'sections' === $parentEntity && $parentIndex > 0 ) {
				$data['quiz_parent_id'] = (int) ( $map['sections'][ $courseIndex ][ $parentIndex ] ?? 0 );
				$data['lesson_id']      = self::resolveSectionQuizLessonId(
					$map,
					$courseIndex,
					$parentIndex,
					(int) ( $data['index'] ?? 1 ),
				);
				$data['topic_id']       = (int) ( $map['section_quiz_topics'][ $courseIndex ][ $parentIndex ] ?? 0 );
			} elseif ( 'modules' === $parentEntity && $parentIndex > 0 ) {
				$data['quiz_parent_id'] = (int) ( $map['modules'][ $courseIndex ][ $parentIndex ] ?? 0 );
			} elseif ( isset( $data['lesson_index'] ) ) {
				$lessonIndex            = (int) $data['lesson_index'];
				$data['quiz_parent_id'] = (int) ( $map['lessons'][ $courseIndex ][ $lessonIndex ] ?? 0 );
				$data['lesson_id']      = $data['quiz_parent_id'];
				$data['topic_id']       = (int) ( $map['lesson_quiz_topics'][ $courseIndex ][ $lessonIndex ] ?? 0 );
			}//end if
		}//end if

		return $data;
	}

	/**
	 * Records the first created ID of a course, lesson, group or group admin item in the process ID map.
	 *
	 * @param string               $processId  ID of the seeding process.
	 * @param string               $type       Queue item type.
	 * @param array<string, mixed> $data       Queue item payload containing the item indices.
	 * @param array<int, int>      $ids        IDs created for the item; only the first is stored.
	 * @param ProcessRepository    $repository Repository holding the process ID map.
	 * @return void
	 */
	public static function record(
		string $processId,
		string $type,
		array $data,
		array $ids,
		ProcessRepository $repository,
	): void {
		if ( array() === $ids ) {
			return;
		}

		$map = $repository->getIdMap( $processId );

		match ( $type ) {
			'course'      => $map['courses'][ (int) ( $data['index'] ?? 0 ) ] = $ids[0],
			'lesson'      => $map['lessons'][ (int) ( $data['course_index'] ?? 0 ) ][ (int) ( $data['index'] ?? 0 ) ] = $ids[0],
			'group'       => $map['groups'][ (int) ( $data['index'] ?? 0 ) ]       = $ids[0],
			'group_admin' => $map['group_admins'][ (int) ( $data['index'] ?? 0 ) ] = $ids[0],
			default       => null,
		};

		$repository->saveIdMap( $processId, $map );
	}

	/**
	 * Records the IDs of quiz parent entities, using the process ID and indices found in the options.
	 *
	 * @param string               $entity    Parent entity name, such as topics, sections or modules.
	 * @param array<int, int>      $parentIds Created parent IDs; the position plus one is used as the parent index.
	 * @param array<string, mixed> $options   Step options containing process_id, course_index and index.
	 * @return void
	 */
	public static function recordQuizParentsFromOptions(
		string $entity,
		array $parentIds,
		array $options,
	): void {
		$processId = (string) ( $options['process_id'] ?? '' );

		if ( '' === $processId || array() === $parentIds ) {
			return;
		}

		$repository  = new ProcessRepository();
		$courseIndex = (int) ( $options['course_index'] ?? 0 );
		$lessonIndex = (int) ( $options['index'] ?? 0 );

		foreach ( $parentIds as $offset => $parentId ) {
			$parentIndex = $offset + 1;

			if ( 'topics' === $entity ) {
				self::recordQuizParent( $processId, $entity, $courseIndex, $parentIndex, $parentId, $repository, $lessonIndex );

				continue;
			}

			self::recordQuizParent( $processId, $entity, $courseIndex, $parentIndex, $parentId, $repository );
		}
	}

	/**
	 * Stores the ID of a single quiz parent entity in the process ID map.
	 *
	 * @param string            $processId   ID of the seeding process.
	 * @param string            $entity      Parent entity name, such as topics, sections or modules.
	 * @param int               $courseIndex One-based index of the course.
	 * @param int               $parentIndex One-based index of the parent within its course, or lesson for topics.
	 * @param int               $id          Created post ID of the parent.
	 * @param ProcessRepository $repository  Repository holding the process ID map.
	 * @param int|null          $lessonIndex One-based lesson index, required to store topics.
	 * @return void
	 */
	public static function recordQuizParent(
		string $processId,
		string $entity,
		int $courseIndex,
		int $parentIndex,
		int $id,
		ProcessRepository $repository,
		?int $lessonIndex = null,
	): void {
		if ( $id <= 0 || $parentIndex <= 0 || $courseIndex <= 0 ) {
			return;
		}

		$map = $repository->getIdMap( $processId );

		if ( 'topics' === $entity && null !== $lessonIndex && $lessonIndex > 0 ) {
			$map['topics'][ $courseIndex ][ $lessonIndex ][ $parentIndex ] = $id;
		} else {
			$map[ $entity ][ $courseIndex ][ $parentIndex ] = $id;
		}

		$repository->saveIdMap( $processId, $map );
	}

	/**
	 * Stores the topic ID created for a section quiz in the process ID map.
	 *
	 * @param string            $processId    ID of the seeding process.
	 * @param int               $courseIndex  One-based index of the course.
	 * @param int               $sectionIndex One-based index of the section.
	 * @param int               $topicId      Created topic post ID.
	 * @param ProcessRepository $repository   Repository holding the process ID map.
	 * @return void
	 */
	public static function recordSectionQuizTopic(
		string $processId,
		int $courseIndex,
		int $sectionIndex,
		int $topicId,
		ProcessRepository $repository,
	): void {
		if ( '' === $processId || $topicId <= 0 || $courseIndex <= 0 || $sectionIndex <= 0 ) {
			return;
		}

		$map = $repository->getIdMap( $processId );
		$map['section_quiz_topics'][ $courseIndex ][ $sectionIndex ] = $topicId;
		$repository->saveIdMap( $processId, $map );
	}

	/**
	 * Stores the topic ID created for a lesson quiz in the process ID map.
	 *
	 * @param string            $processId   ID of the seeding process.
	 * @param int               $courseIndex One-based index of the course.
	 * @param int               $lessonIndex One-based index of the lesson.
	 * @param int               $topicId     Created topic post ID.
	 * @param ProcessRepository $repository  Repository holding the process ID map.
	 * @return void
	 */
	public static function recordLessonQuizTopic(
		string $processId,
		int $courseIndex,
		int $lessonIndex,
		int $topicId,
		ProcessRepository $repository,
	): void {
		if ( '' === $processId || $topicId <= 0 || $courseIndex <= 0 || $lessonIndex <= 0 ) {
			return;
		}

		$map = $repository->getIdMap( $processId );
		$map['lesson_quiz_topics'][ $courseIndex ][ $lessonIndex ] = $topicId;
		$repository->saveIdMap( $processId, $map );
	}

	/**
	 * Appends a lesson ID to the list of lessons belonging to a section in the process ID map.
	 *
	 * @param string            $processId    ID of the seeding process.
	 * @param int               $courseIndex  One-based index of the course.
	 * @param int               $sectionIndex One-based index of the section.
	 * @param int               $lessonId     Created lesson post ID.
	 * @param ProcessRepository $repository   Repository holding the process ID map.
	 * @return void
	 */
	public static function recordSectionLesson(
		string $processId,
		int $courseIndex,
		int $sectionIndex,
		int $lessonId,
		ProcessRepository $repository,
	): void {
		if ( '' === $processId || $lessonId <= 0 || $courseIndex <= 0 || $sectionIndex <= 0 ) {
			return;
		}

		$map = $repository->getIdMap( $processId );

		if ( ! isset( $map['section_lessons'][ $courseIndex ][ $sectionIndex ] ) || ! is_array( $map['section_lessons'][ $courseIndex ][ $sectionIndex ] ) ) {
			$map['section_lessons'][ $courseIndex ][ $sectionIndex ] = array();
		}

		$map['section_lessons'][ $courseIndex ][ $sectionIndex ][] = $lessonId;
		$repository->saveIdMap( $processId, $map );
	}

	/**
	 * Picks the lesson of a section that a quiz should follow, counting back from the last lesson by quiz index.
	 *
	 * @param array<string, mixed> $map          Process ID map.
	 * @param int                  $courseIndex  One-based index of the course.
	 * @param int                  $sectionIndex One-based index of the section.
	 * @param int                  $quizIndex    One-based index of the quiz within the section.
	 * @return int Lesson post ID, or 0 if the section has no lessons.
	 */
	private static function resolveSectionQuizLessonId(
		array $map,
		int $courseIndex,
		int $sectionIndex,
		int $quizIndex,
	): int {
		$lessons = $map['section_lessons'][ $courseIndex ][ $sectionIndex ] ?? array();

		if ( ! is_array( $lessons ) || array() === $lessons ) {
			return 0;
		}

		$position = count( $lessons ) - max( 1, $quizIndex );

		return (int) ( $lessons[ max( 0, $position ) ] ?? end( $lessons ) );
	}

	/**
	 * Deletes the ID map of a seeding process.
	 *
	 * @param string            $processId  ID of the seeding process.
	 * @param ProcessRepository $repository Repository holding the process ID map.
	 * @return void
	 */
	public static function delete( string $processId, ProcessRepository $repository ): void {
		$repository->deleteIdMap( $processId );
	}
}
