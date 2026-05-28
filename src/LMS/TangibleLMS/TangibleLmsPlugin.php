<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\TangibleLMS;

use Tangible\Populater\Registry\AbstractLmsPlugin;

class TangibleLmsPlugin extends AbstractLmsPlugin
{
    protected string $slug = 'tangible-lms';
    protected string $name = 'Tangible LMS';
    protected string $pluginFile = 'tangible-lms/tangible-lms.php';
    protected string $seederClass = TangibleLMSSeeder::class;
    protected string $backgroundAction = 'seed_tangible_lms';
}
