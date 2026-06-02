<?php

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Builds unique, predictable post titles and slugs for seeded LMS content.
 *
 * Titles encode hierarchy (course / lesson / topic / quiz indices) so k6 and
 * other load tests can derive permalinks without querying the database.
 */
final class DeterministicTitle
{
    public static function course(string $prefix, int $index): string
    {
        return sprintf('%s %d', $prefix, $index);
    }

    public static function lesson(string $prefix, int $courseIndex, int $lessonIndex): string
    {
        return sprintf('%s C%d L%d', $prefix, $courseIndex, $lessonIndex);
    }

    public static function topic(string $prefix, int $courseIndex, int $lessonIndex, int $topicIndex): string
    {
        return sprintf('%s C%d L%d T%d', $prefix, $courseIndex, $lessonIndex, $topicIndex);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function quiz(string $prefix, int $courseIndex, array $options): string
    {
        $quizIndex     = (int) ($options['index'] ?? 1);
        $parentEntity  = (string) ($options['quiz_parent_entity'] ?? '');
        $parentIndex   = (int) ($options['quiz_parent_index'] ?? 0);
        $lessonIndex   = (int) ($options['lesson_index'] ?? 0);

        if ($parentEntity === 'topics' && $parentIndex > 0 && $lessonIndex > 0) {
            return sprintf('%s C%d L%d T%d Q%d', $prefix, $courseIndex, $lessonIndex, $parentIndex, $quizIndex);
        }

        if ($parentEntity === 'sections' && $parentIndex > 0) {
            return sprintf('%s C%d S%d Q%d', $prefix, $courseIndex, $parentIndex, $quizIndex);
        }

        if ($parentEntity === 'modules' && $parentIndex > 0) {
            return sprintf('%s C%d M%d Q%d', $prefix, $courseIndex, $parentIndex, $quizIndex);
        }

        if ($lessonIndex > 0) {
            return sprintf('%s C%d L%d Q%d', $prefix, $courseIndex, $lessonIndex, $quizIndex);
        }

        return sprintf('%s C%d Q%d', $prefix, $courseIndex, $quizIndex);
    }

    /**
     * @param array<string, mixed> $options  Same context as {@see quiz()}.
     */
    public static function question(string $prefix, int $courseIndex, array $options, int $questionIndex): string
    {
        return self::quiz($prefix, $courseIndex, $options) . ' N' . $questionIndex;
    }

    public static function section(string $prefix, int $courseIndex, int $sectionIndex): string
    {
        return sprintf('%s C%d S%d', $prefix, $courseIndex, $sectionIndex);
    }

    public static function module(string $prefix, int $courseIndex, int $moduleIndex): string
    {
        return sprintf('%s C%d M%d', $prefix, $courseIndex, $moduleIndex);
    }

    public static function group(string $prefix, int $index): string
    {
        return sprintf('%s %d', $prefix, $index);
    }

    public static function certificate(string $prefix, int $index): string
    {
        return sprintf('%s %d', $prefix, $index);
    }

    public static function slug(string $title): string
    {
        if (function_exists('sanitize_title')) {
            return sanitize_title($title);
        }

        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'item';
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function resolveIndex(array $options, int $loopIndex): int
    {
        return (int) ($options['index'] ?? $loopIndex);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function courseIndex(array $options): int
    {
        return max(0, (int) ($options['course_index'] ?? 0));
    }
}
