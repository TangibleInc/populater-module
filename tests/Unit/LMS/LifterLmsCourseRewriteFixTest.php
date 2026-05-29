<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\LMS;

use Tangible\Populater\LMS\LifterLMS\LifterLmsCourseRewriteFix;
use Brain\Monkey\Functions;

class LifterLmsCourseRewriteFixTest extends \WPTestCase
{
    public function test_remap_course_request_when_only_lifter_course_exists(): void
    {
        $slug = 'lifterlms-course-1-5';

        Functions\when('get_page_by_path')->alias(function (string $path, string $output, string $type) {
            if ($type === 'lms_course') {
                return null;
            }

            if ($type === 'course' && $path === 'lifterlms-course-1-5') {
                return (object) ['ID' => 123];
            }

            return null;
        });

        $result = LifterLmsCourseRewriteFix::remapCourseRequest([
            'lms_course' => $slug,
            'post_type'  => 'lms_course',
            'name'       => $slug,
        ]);

        $this->assertArrayNotHasKey('lms_course', $result);
        $this->assertSame($slug, $result['course']);
        $this->assertSame($slug, $result['name']);
        $this->assertSame('course', $result['post_type']);
    }

    public function test_remap_skips_when_tangible_course_exists(): void
    {
        $slug = 'shared-slug';

        Functions\when('get_page_by_path')->alias(function (string $path, string $output, string $type) {
            if ($path === 'shared-slug') {
                return (object) ['ID' => $type === 'lms_course' ? 1 : 2];
            }

            return null;
        });

        $input = [
            'lms_course' => $slug,
            'post_type'  => 'lms_course',
        ];

        $this->assertSame($input, LifterLmsCourseRewriteFix::remapCourseRequest($input));
    }

    public function test_remap_leaves_unrelated_requests_unchanged(): void
    {
        $input = ['pagename' => 'about'];

        $this->assertSame($input, LifterLmsCourseRewriteFix::remapCourseRequest($input));
    }
}
