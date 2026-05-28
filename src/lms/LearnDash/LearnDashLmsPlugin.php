<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LearnDash;

use Tangible\Populater\Registry\AbstractLmsPlugin;

class LearnDashLmsPlugin extends AbstractLmsPlugin
{
    protected string $slug = 'learndash';
    protected string $name = 'LearnDash LMS';
    protected string $pluginFile = 'sfwd-lms/sfwd_lms.php';
    protected string $seederClass = LearnDashSeeder::class;
    protected string $processClass = LearnDashSeedingProcess::class;
    protected string $backgroundAction = 'seed_learndash';
}
