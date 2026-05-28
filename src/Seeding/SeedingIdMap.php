<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Tracks created post IDs per seeding process so later queue items can resolve
 * course_index / lesson_index into real course_id / lesson_id values.
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
        ?ProcessRepository $repository = null,
    ): array {
        $repository ??= new ProcessRepository();
        $map          = $repository->getIdMap($processId);

        if ($type === 'lesson') {
            $courseIndex       = (int) ($data['course_index'] ?? 0);
            $data['course_id'] = (int) ($map['courses'][$courseIndex] ?? 0);
        }

        if ($type === 'quiz') {
            $courseIndex       = (int) ($data['course_index'] ?? 0);
            $lessonIndex       = (int) ($data['lesson_index'] ?? 0);
            $data['lesson_id'] = (int) ($map['lessons'][$courseIndex][$lessonIndex] ?? 0);
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
        ?ProcessRepository $repository = null,
    ): void {
        if ($ids === []) {
            return;
        }

        $repository ??= new ProcessRepository();
        $map          = $repository->getIdMap($processId);

        match ($type) {
            'course' => $map['courses'][(int) ($data['index'] ?? 0)] = $ids[0],
            'lesson' => $map['lessons'][(int) ($data['course_index'] ?? 0)][(int) ($data['index'] ?? 0)] = $ids[0],
            default  => null,
        };

        $repository->saveIdMap($processId, $map);
    }

    public static function delete(string $processId, ?ProcessRepository $repository = null): void
    {
        ($repository ?? new ProcessRepository())->deleteIdMap($processId);
    }
}
