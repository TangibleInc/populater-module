<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Integration\REST;

use Tangible\Populater\Seeding\SeedingStatus;
use Tangible\Populater\Tests\Integration\WordPressIntegrationTestCase;
use Tangible\Populater\Tests\Support\BackgroundProcessDrainer;
use Tangible\Populater\Tests\Support\LmsContentInspector;
use Tangible\Populater\Tests\Support\PluginTestAccessor;

/**
 * REST seeding integration tests against a live WordPress database.
 *
 * @group integration
 * @group rest
 */
class SeedRestIntegrationTest extends WordPressIntegrationTestCase
{
    private const COURSES   = 2;
    private const LESSONS   = 3;
    private const QUIZZES   = 2;
    private const QUESTIONS = 3;
    private const TOPICS    = 2;
    private const SECTIONS  = 1;
    private const MODULES   = 1;
    private const USERS     = 4;

    /**
     * @return array<string, array{0: string}>
     */
    public static function activeLmsProvider(): array
    {
        return [
            'learndash'    => ['learndash'],
            'lifterlms'    => ['lifterlms'],
            'tangible-lms' => ['tangible-lms'],
        ];
    }

    /**
     * @dataProvider activeLmsProvider
     */
    public function test_post_seed_creates_expected_entities_in_database(string $plugin): void
    {
        if (!$this->isPluginActive($plugin)) {
            $this->markTestSkipped(sprintf('Plugin "%s" is not active in this environment.', $plugin));
        }

        $manager   = PluginTestAccessor::seedingManager();
        $before    = LmsContentInspector::snapshot($plugin);
        $maxPostId = $this->maxPostId();

        $start = $this->restRequest('POST', '/tangible-populater/v1/seed', [
            'plugin'             => $plugin,
            'courses'            => self::COURSES,
            'lessons_per_course' => self::LESSONS,
            'quizzes_per_lesson' => self::QUIZZES,
            'users'              => self::USERS,
        ]);

        $startData = $this->restData($start);
        $this->assertArrayHasKey('process_id', $startData);

        $processId = (string) $startData['process_id'];
        $expected  = LmsContentInspector::expectedCounts(
            self::COURSES,
            self::LESSONS,
            self::QUIZZES,
            self::USERS,
            self::QUESTIONS,
            self::TOPICS,
            self::SECTIONS,
            self::MODULES,
        );
        $expectedTotal = $expected['courses'] + $expected['lessons'] + $expected['quizzes'] + $expected['users'];

        $finalStatus = BackgroundProcessDrainer::runSeedToCompletion($manager, $plugin, $processId);

        $this->assertSame(
            SeedingStatus::STATUS_COMPLETED,
            $finalStatus->getStatus(),
            'Seeding did not complete: ' . ($finalStatus->getError() ?? $finalStatus->getStatus()),
        );
        $this->assertSame($expectedTotal, $finalStatus->getTotal());
        $this->assertSame($expectedTotal, $finalStatus->getProcessed());

        $delta = LmsContentInspector::diff($before, LmsContentInspector::snapshot($plugin));

        LmsContentInspector::assertSeededCounts($this, $plugin, $delta, $expected);
        LmsContentInspector::assertSeededStructure(
            $this,
            $plugin,
            self::COURSES,
            self::LESSONS,
            self::QUIZZES,
            $maxPostId,
            self::QUESTIONS,
            self::TOPICS,
            self::SECTIONS,
            self::MODULES,
        );

        $statusResponse = $this->restRequest('GET', '/tangible-populater/v1/seed/' . $processId . '/status');
        $statusData     = $this->restData($statusResponse);

        $this->assertSame(SeedingStatus::STATUS_COMPLETED, $statusData['status']);
        $this->assertSame($expectedTotal, $statusData['total']);
        $this->assertSame($expectedTotal, $statusData['processed']);

        $logsResponse = $this->restRequest('GET', '/tangible-populater/v1/seed/' . $processId . '/logs');
        $logsData     = $this->restData($logsResponse);

        $this->assertNotEmpty($logsData['logs'], 'Seeding logs should not be empty.');
        $this->assertGreaterThan($maxPostId, $this->maxPostId(), 'Expected new posts to be created.');
    }

    public function test_get_plugins_lists_supported_lms_plugins(): void
    {
        $response = $this->restRequest('GET', '/tangible-populater/v1/plugins');
        $data     = $this->restData($response);

        $this->assertArrayHasKey('plugins', $data);
        $this->assertNotEmpty($data['plugins']);

        $slugs = array_column($data['plugins'], 'slug');
        $this->assertContains('learndash', $slugs);
        $this->assertContains('lifterlms', $slugs);
        $this->assertContains('tangible-lms', $slugs);
    }

    public function test_post_seed_rejects_unknown_plugin(): void
    {
        $request = new \WP_REST_Request('POST', '/tangible-populater/v1/seed');
        $request->set_param('plugin', 'not-a-real-lms');
        $request->set_param('courses', 1);

        $response = rest_do_request($request);

        $this->assertGreaterThanOrEqual(400, $response->get_status());
        $error = $response->as_error();
        $this->assertSame('invalid_plugin', $error->get_error_code());
    }

    public function test_post_seed_cancel_stops_running_process(): void
    {
        if (!$this->isPluginActive('tangible-lms')) {
            $this->markTestSkipped('Tangible LMS is not active in this environment.');
        }

        $start = $this->restRequest('POST', '/tangible-populater/v1/seed', [
            'plugin'             => 'tangible-lms',
            'courses'            => 5,
            'lessons_per_course' => 5,
            'quizzes_per_lesson' => 1,
            'users'              => 10,
        ]);

        $processId = (string) $this->restData($start)['process_id'];

        $cancel = $this->restRequest('POST', '/tangible-populater/v1/seed/' . $processId . '/cancel');
        $this->assertTrue($this->restData($cancel)['cancelled']);

        $status = $this->restData(
            $this->restRequest('GET', '/tangible-populater/v1/seed/' . $processId . '/status'),
        );

        $this->assertSame(SeedingStatus::STATUS_CANCELLED, $status['status']);
    }

    private function isPluginActive(string $slug): bool
    {
        return PluginTestAccessor::seedingManager()
            ->getSupportedPlugins()
            !== [] && in_array(
                $slug,
                array_column(PluginTestAccessor::seedingManager()->getSupportedPlugins(), 'slug'),
                true,
            )
            && array_values(array_filter(
                PluginTestAccessor::seedingManager()->getSupportedPlugins(),
                static fn(array $plugin) => $plugin['slug'] === $slug && $plugin['active'],
            )) !== [];
    }

    private function maxPostId(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
    }
}
