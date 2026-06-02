<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Support;

use Tangible\Populater\Support\SeededUserProfile;
use Brain\Monkey\Functions;

class SeededUserProfileTest extends \WPTestCase
{
    public function test_populate_sets_student_names(): void
    {
        $this->expectNotToPerformAssertions();
        Functions\expect('update_user_meta')
            ->once()
            ->with(7, 'first_name', 'Student')
            ->andReturn(true);
        Functions\expect('update_user_meta')
            ->once()
            ->with(7, 'last_name', '3')
            ->andReturn(true);
        Functions\expect('wp_update_user')
            ->once()
            ->with([
                'ID'           => 7,
                'display_name' => 'Student 3',
            ])
            ->andReturn(7);

        SeededUserProfile::populate(7, 3, 'student');
    }

    public function test_populate_sets_group_admin_names(): void
    {
        $this->expectNotToPerformAssertions();
        Functions\expect('update_user_meta')
            ->once()
            ->with(9, 'first_name', 'Group')
            ->andReturn(true);
        Functions\expect('update_user_meta')
            ->once()
            ->with(9, 'last_name', 'Admin 2')
            ->andReturn(true);
        Functions\expect('wp_update_user')
            ->once()
            ->with([
                'ID'           => 9,
                'display_name' => 'Group Admin 2',
            ])
            ->andReturn(9);

        SeededUserProfile::populate(9, 2, 'groupadmin');
    }

    public function test_populate_skips_invalid_user_id(): void
    {
        $this->expectNotToPerformAssertions();
        Functions\expect('update_user_meta')->never();
        Functions\expect('wp_update_user')->never();

        SeededUserProfile::populate(0, 1);
    }
}
