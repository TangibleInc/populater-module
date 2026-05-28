<?php

declare(strict_types=1);

namespace Tangible\Populater\Registry;

/**
 * Metadata for one supported LMS plugin.
 */
final class LmsPluginDefinition
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $pluginFile,
        /** @var class-string<\Tangible\Populater\Seeders\AbstractSeeder> */
        public readonly string $seederClass,
        /** @var class-string<\Tangible\Populater\Seeding\AbstractSeeding> */
        public readonly string $processClass,
        public readonly string $backgroundAction,
    ) {}
}
