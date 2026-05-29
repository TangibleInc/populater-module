<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

/**
 * Resolves /course/{slug}/ conflicts when Tangible LMS and LifterLMS are both active.
 *
 * Both plugins register a public course post type with rewrite slug "course". WordPress
 * rewrite rules then use the Tangible query var (lms_course), so LifterLMS courses 404.
 */
final class LifterLmsCourseRewriteFix
{
    public static function register(): void
    {
        if (!function_exists('is_plugin_active')) {
            return;
        }

        if (!is_plugin_active('lifterlms/lifterlms.php') || !is_plugin_active('tangible-lms/tangible-lms.php')) {
            return;
        }

        add_filter('request', [self::class, 'remapCourseRequest'], 1);
    }

    /**
     * @param array<string, mixed> $query_vars
     * @return array<string, mixed>
     */
    public static function remapCourseRequest(array $query_vars): array
    {
        if (
            !isset($query_vars['lms_course'])
            || !is_string($query_vars['lms_course'])
            || $query_vars['lms_course'] === ''
        ) {
            return $query_vars;
        }

        if (isset($query_vars['post_type']) && $query_vars['post_type'] !== 'lms_course') {
            return $query_vars;
        }

        $slug = $query_vars['lms_course'];

        if (self::postExistsByPath($slug, 'lms_course')) {
            return $query_vars;
        }

        if (!self::postExistsByPath($slug, 'course')) {
            return $query_vars;
        }

        unset($query_vars['lms_course']);

        $query_vars['course']    = $slug;
        $query_vars['name']      = $slug;
        $query_vars['post_type'] = 'course';

        return $query_vars;
    }

    private static function postExistsByPath(string $slug, string $postType): bool
    {
        $post = get_page_by_path($slug, 'OBJECT', $postType);

        return is_object($post) && isset($post->ID) && (int) $post->ID > 0;
    }
}
