<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\LMS;

use Tangible\Populater\LMS\LifterLMS\LifterLmsStudentProfile;
use Brain\Monkey\Functions;

class LifterLmsStudentProfileTest extends \WPTestCase
{
    public function test_populate_sets_profile_and_billing_meta(): void
    {
        $this->expectNotToPerformAssertions();
        Functions\expect('update_user_meta')->times(7)->andReturn(true);
        Functions\expect('wp_update_user')->once()->andReturn(5);

        LifterLmsStudentProfile::populate(5, 4, 'student');
    }
}
