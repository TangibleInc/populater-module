<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Support;

use Tangible\Populater\Support\SeededUsername;

class SeededUsernameTest extends \PHPUnit\Framework\TestCase
{
    public function test_lifterlms_student_username(): void
    {
        $this->assertSame('lifterstudent99', SeededUsername::username('lifterlms', 'student', 99));
        $this->assertSame('lifterstudent99@example.com', SeededUsername::email('lifterlms', 'student', 99));
    }

    public function test_lifterlms_group_admin_username(): void
    {
        $this->assertSame('liftergroupadmin3', SeededUsername::username('lifterlms', 'groupadmin', 3));
    }

    public function test_learndash_student_username(): void
    {
        $this->assertSame('ldstudent2', SeededUsername::username('learndash', 'student', 2));
        $this->assertSame('ldgroupadmin1', SeededUsername::username('learndash', 'groupadmin', 1));
    }

    public function test_unknown_plugin_falls_back_to_role_type(): void
    {
        $this->assertSame('student5', SeededUsername::username('test-lms', 'student', 5));
    }
}
