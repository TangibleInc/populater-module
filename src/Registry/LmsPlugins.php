<?php

declare(strict_types=1);

namespace Tangible\Populater\Registry;

use Tangible\Populater\LMS\LearnDash\LearnDashIntegration;
use Tangible\Populater\LMS\LearnDash\LearnDashLmsPlugin;
use Tangible\Populater\LMS\LifterLMS\LifterLMSIntegration;
use Tangible\Populater\LMS\LifterLMS\LifterLmsPlugin;
use Tangible\Populater\LMS\TangibleLMS\TangibleLMSIntegration;
use Tangible\Populater\LMS\TangibleLMS\TangibleLmsPlugin;

/**
 * Boots built-in LMS plugin registrations.
 */
final class LmsPlugins
{
    public static function registerBuiltIn(): void
    {
        new LearnDashLmsPlugin();
        new LifterLmsPlugin();
        new TangibleLmsPlugin();

        LearnDashIntegration::register();
        LifterLMSIntegration::register();
        TangibleLMSIntegration::register();
    }
}
