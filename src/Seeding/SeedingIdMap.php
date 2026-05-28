<?php

declare(strict_types=1);

namespace Tangible\Populater\Seeding;

/**
 * Tracks created post IDs per seeding process so later queue items can resolve
 * course_index / lesson_index into real course_id / lesson_id values.
 */
final class SeedingIdMap
{
    private const OPTION_PREFIX = 'tangible_populater_ids_';

    /**
     * Resolves index fields to IDs using data recorded from earlier queue items.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function enrich(string $processId, string $type, array $data): array
    {
        $map = self::load($processId);

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
    public static function record(string $processId, string $type, array $data, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $map = self::load($processId);

        match ($type) {
            'course' => $map['courses'][(int) ($data['index'] ?? 0)] = $ids[0],
            'lesson' => $map['lessons'][(int) ($data['course_index'] ?? 0)][(int) ($data['index'] ?? 0)] = $ids[0],
            default  => null,
        };

        self::save($processId, $map);
    }

    public static function delete(string $processId): void
    {
        delete_option(self::OPTION_PREFIX . $processId);
    }

    /** @return array{courses: array<int, int>, lessons: array<int, array<int, int>>} */
    private static function load(string $processId): array
    {
        $stored = get_option(self::OPTION_PREFIX . $processId, null);

        if (!is_array($stored)) {
            return ['courses' => [], 'lessons' => []];
        }

        return [
            'courses' => is_array($stored['courses'] ?? null) ? $stored['courses'] : [],
            'lessons' => is_array($stored['lessons'] ?? null) ? $stored['lessons'] : [],
        ];
    }

    /** @param array{courses: array<int, int>, lessons: array<int, array<int, int>>} $map */
    private static function save(string $processId, array $map): void
    {
        update_option(self::OPTION_PREFIX . $processId, $map, false);
    }
}
