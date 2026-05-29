<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Integration\REST;

use Tangible\Populater\Tests\Integration\WordPressIntegrationTestCase;
use Tangible\Populater\Tests\Support\BackgroundProcessDrainer;
use Tangible\Populater\Tests\Support\LmsContentInspector;
use Tangible\Populater\Tests\Support\PluginTestAccessor;

/**
 * @group integration
 * @group rest
 */
class ResetRestIntegrationTest extends WordPressIntegrationTestCase
{
    public function test_post_reset_removes_seeded_content(): void
    {
        if (!$this->isTangibleLmsActive()) {
            $this->markTestSkipped('Tangible LMS is not active in this environment.');
        }

        $manager = PluginTestAccessor::seedingManager();
        $plugin  = 'tangible-lms';

        $start = $this->restRequest('POST', '/tangible-populater/v1/seed', [
            'plugin'             => $plugin,
            'courses'            => 1,
            'lessons_per_course' => 1,
            'quizzes_per_section' => 1,
            'users'              => 1,
        ]);

        $processId = (string) $this->restData($start)['process_id'];
        BackgroundProcessDrainer::runSeedToCompletion($manager, $plugin, $processId);

        $afterSeed = LmsContentInspector::snapshot($plugin);
        $this->assertGreaterThan(0, $afterSeed['courses'] + $afterSeed['lessons'] + $afterSeed['quizzes'] + $afterSeed['users']);

        $reset = $this->restRequest('POST', '/tangible-populater/v1/reset', [
            'confirmed' => true,
        ]);

        $resetData = $this->restData($reset);
        $this->assertTrue($resetData['success']);

        $afterReset = LmsContentInspector::snapshot($plugin);
        $this->assertSame(0, $afterReset['courses']);
        $this->assertSame(0, $afterReset['lessons']);
        $this->assertSame(0, $afterReset['quizzes']);
        $this->assertSame(0, $afterReset['users']);
    }

    public function test_post_reset_without_confirmation_is_aborted(): void
    {
        $response = $this->restRequest('POST', '/tangible-populater/v1/reset', [
            'confirmed' => false,
        ]);

        $data = $this->restData($response);
        $this->assertFalse($data['success']);
    }

    private function isTangibleLmsActive(): bool
    {
        foreach (PluginTestAccessor::seedingManager()->getSupportedPlugins() as $plugin) {
            if ($plugin['slug'] === 'tangible-lms' && $plugin['active']) {
                return true;
            }
        }

        return false;
    }
}
