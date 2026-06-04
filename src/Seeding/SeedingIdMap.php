<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Tracks created post IDs per seeding process so later queue items can resolve
 * course_index / lesson_index / section or topic indices into real IDs.
 */
final class SeedingIdMap
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function enrich(
        string $processId,
        string $type,
        array $data,
        ProcessRepository $repository,
    ): array {
        $map = $repository->getIdMap($processId);

        if ($type === 'lesson') {
            $courseIndex       = (int) ($data['course_index'] ?? 0);
            $data['course_id'] = (int) ($map['courses'][$courseIndex] ?? 0);
        }

        if (in_array($type, ['course', 'user', 'group_admin'], true)) {
            $groupIndex = (int) ($data['group_index'] ?? ($type === 'group_admin' ? ($data['index'] ?? 0) : 0));

            if ($groupIndex > 0) {
                $data['group_index'] = $groupIndex;
                $data['group_id']    = (int) ($map['groups'][$groupIndex] ?? 0);
            }
        }

        if ($type === 'quiz') {
            $courseIndex       = (int) ($data['course_index'] ?? 0);
            $data['course_id'] = (int) ($map['courses'][$courseIndex] ?? 0);
            $parentEntity      = (string) ($data['quiz_parent_entity'] ?? '');
            $parentIndex       = (int) ($data['quiz_parent_index'] ?? 0);

            if ($parentEntity === 'topics' && $parentIndex > 0) {
                $lessonIndex         = (int) ($data['lesson_index'] ?? 0);
                $data['lesson_id']   = (int) ($map['lessons'][$courseIndex][$lessonIndex] ?? 0);
                $data['quiz_parent_id'] = (int) ($map['topics'][$courseIndex][$lessonIndex][$parentIndex] ?? 0);
            } elseif ($parentEntity === 'sections' && $parentIndex > 0) {
                $data['quiz_parent_id'] = (int) ($map['sections'][$courseIndex][$parentIndex] ?? 0);
                $data['lesson_id']      = self::resolveSectionQuizLessonId(
                    $map,
                    $courseIndex,
                    $parentIndex,
                    (int) ($data['index'] ?? 1),
                );
                $data['topic_id']       = (int) ($map['section_quiz_topics'][$courseIndex][$parentIndex] ?? 0);
            } elseif ($parentEntity === 'modules' && $parentIndex > 0) {
                $data['quiz_parent_id'] = (int) ($map['modules'][$courseIndex][$parentIndex] ?? 0);
            } elseif (isset($data['lesson_index'])) {
                $lessonIndex            = (int) $data['lesson_index'];
                $data['quiz_parent_id'] = (int) ($map['lessons'][$courseIndex][$lessonIndex] ?? 0);
                $data['lesson_id']      = $data['quiz_parent_id'];
                $data['topic_id']       = (int) ($map['lesson_quiz_topics'][$courseIndex][$lessonIndex] ?? 0);
            }
        }

        return $data;
    }

    /**
     * @param list<int>            $ids
     * @param array<string, mixed> $data
     */
    public static function record(
        string $processId,
        string $type,
        array $data,
        array $ids,
        ProcessRepository $repository,
    ): void {
        if ($ids === []) {
            return;
        }

        $map = $repository->getIdMap($processId);

        match ($type) {
            'course'      => $map['courses'][(int) ($data['index'] ?? 0)] = $ids[0],
            'lesson'      => $map['lessons'][(int) ($data['course_index'] ?? 0)][(int) ($data['index'] ?? 0)] = $ids[0],
            'group'       => $map['groups'][(int) ($data['index'] ?? 0)] = $ids[0],
            'group_admin' => $map['group_admins'][(int) ($data['index'] ?? 0)] = $ids[0],
            default       => null,
        };

        $repository->saveIdMap($processId, $map);
    }

    /**
     * @param list<int>            $parentIds
     * @param array<string, mixed> $options
     */
    public static function recordQuizParentsFromOptions(
        string $entity,
        array $parentIds,
        array $options,
    ): void {
        $processId = (string) ($options['process_id'] ?? '');

        if ($processId === '' || $parentIds === []) {
            return;
        }

        $repository  = new ProcessRepository();
        $courseIndex = (int) ($options['course_index'] ?? 0);
        $lessonIndex = (int) ($options['index'] ?? 0);

        foreach ($parentIds as $offset => $parentId) {
            $parentIndex = $offset + 1;

            if ($entity === 'topics') {
                self::recordQuizParent($processId, $entity, $courseIndex, $parentIndex, $parentId, $repository, $lessonIndex);

                continue;
            }

            self::recordQuizParent($processId, $entity, $courseIndex, $parentIndex, $parentId, $repository);
        }
    }

    public static function recordQuizParent(
        string $processId,
        string $entity,
        int $courseIndex,
        int $parentIndex,
        int $id,
        ProcessRepository $repository,
        ?int $lessonIndex = null,
    ): void {
        if ($id <= 0 || $parentIndex <= 0 || $courseIndex <= 0) {
            return;
        }

        $map = $repository->getIdMap($processId);

        if ($entity === 'topics' && $lessonIndex !== null && $lessonIndex > 0) {
            $map['topics'][$courseIndex][$lessonIndex][$parentIndex] = $id;
        } else {
            $map[$entity][$courseIndex][$parentIndex] = $id;
        }

        $repository->saveIdMap($processId, $map);
    }

    public static function recordSectionQuizTopic(
        string $processId,
        int $courseIndex,
        int $sectionIndex,
        int $topicId,
        ProcessRepository $repository,
    ): void {
        if ($processId === '' || $topicId <= 0 || $courseIndex <= 0 || $sectionIndex <= 0) {
            return;
        }

        $map = $repository->getIdMap($processId);
        $map['section_quiz_topics'][$courseIndex][$sectionIndex] = $topicId;
        $repository->saveIdMap($processId, $map);
    }

    public static function recordLessonQuizTopic(
        string $processId,
        int $courseIndex,
        int $lessonIndex,
        int $topicId,
        ProcessRepository $repository,
    ): void {
        if ($processId === '' || $topicId <= 0 || $courseIndex <= 0 || $lessonIndex <= 0) {
            return;
        }

        $map = $repository->getIdMap($processId);
        $map['lesson_quiz_topics'][$courseIndex][$lessonIndex] = $topicId;
        $repository->saveIdMap($processId, $map);
    }

    public static function recordSectionLesson(
        string $processId,
        int $courseIndex,
        int $sectionIndex,
        int $lessonId,
        ProcessRepository $repository,
    ): void {
        if ($processId === '' || $lessonId <= 0 || $courseIndex <= 0 || $sectionIndex <= 0) {
            return;
        }

        $map = $repository->getIdMap($processId);

        if (!isset($map['section_lessons'][$courseIndex][$sectionIndex]) || !is_array($map['section_lessons'][$courseIndex][$sectionIndex])) {
            $map['section_lessons'][$courseIndex][$sectionIndex] = [];
        }

        $map['section_lessons'][$courseIndex][$sectionIndex][] = $lessonId;
        $repository->saveIdMap($processId, $map);
    }

    /**
     * @param array<string, mixed> $map
     */
    private static function resolveSectionQuizLessonId(
        array $map,
        int $courseIndex,
        int $sectionIndex,
        int $quizIndex,
    ): int {
        $lessons = $map['section_lessons'][$courseIndex][$sectionIndex] ?? [];

        if (!is_array($lessons) || $lessons === []) {
            return 0;
        }

        $position = count($lessons) - max(1, $quizIndex);

        return (int) ($lessons[max(0, $position)] ?? end($lessons));
    }

    public static function delete(string $processId, ProcessRepository $repository): void
    {
        $repository->deleteIdMap($processId);
    }
}
