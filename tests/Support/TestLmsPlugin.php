<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Support;

use Tangible\Populater\Registry\AbstractLmsPlugin;
use Tangible\Populater\Seeders\AbstractSeeder;

/**
 * Minimal LMS plugin registration for unit tests.
 */
final class TestLmsPlugin extends AbstractLmsPlugin
{
    protected string $slug = 'test-lms';
    protected string $name = 'Test LMS';
    protected string $pluginFile = 'test/test.php';
    protected string $seederClass = AbstractSeeder::class;
    protected string $backgroundAction = 'seed_test';
}
