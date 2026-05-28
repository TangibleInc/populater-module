<?php

declare(strict_types=1);

namespace Tangible\Populater\LMS\LifterLMS;

use Tangible\Populater\Registry\AbstractLmsPlugin;

class LifterLmsPlugin extends AbstractLmsPlugin
{
    protected string $slug = 'lifterlms';
    protected string $name = 'LifterLMS';
    protected string $pluginFile = 'lifterlms/lifterlms.php';
    protected string $seederClass = LifterLMSSeeder::class;
    protected string $processClass = LifterLMSSeedingProcess::class;
    protected string $backgroundAction = 'seed_lifterlms';
}
