<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Integration\LifterLMS;

use Tangible\Populater\Seeding\SeedingStatus;
use Tangible\Populater\Tests\Integration\WordPressIntegrationTestCase;
use Tangible\Populater\Tests\Support\BackgroundProcessDrainer;
use Tangible\Populater\Tests\Support\LmsContentInspector;
use Tangible\Populater\Tests\Support\PluginTestAccessor;

/**
 * Verifies LifterLMS Groups add-on integration during seeding.
 *
 * @group integration
 * @group lifterlms
 * @group groups
 */
class LifterLmsGroupsIntegrationTest extends WordPressIntegrationTestCase
{
    private const GROUPS   = 2;
    private const COURSES  = 4;
    private const LESSONS  = 1;
    private const USERS    = 4;
    private const PASSWORD = 'PopulaterTestPass1!';

    protected function setUp(): void
    {
        if (!function_exists('llms_create_group') || !class_exists(\LLMS_Groups_Enrollment::class)) {
            $this->markTestSkipped('LifterLMS Groups add-on is not active in this environment.');
        }

        if (!$this->isLifterLmsActive()) {
            $this->markTestSkipped('LifterLMS is not active in this environment.');
        }

        parent::setUp();
    }

    public function test_seeding_creates_groups_assigns_admins_and_members(): void
    {
        $manager   = PluginTestAccessor::seedingManager();
        $maxPostId = $this->maxPostId();

        $seedParams = [
            'plugin'              => 'lifterlms',
            'courses'             => self::COURSES,
            'lessons_per_course'  => self::LESSONS,
            'quizzes_per_section' => 0,
            'questions_per_quiz'  => 0,
            'users'               => self::USERS,
            'groups'              => self::GROUPS,
            'user_password'       => self::PASSWORD,
        ];

        $start = $this->restRequest('POST', '/tangible-populater/v1/seed', $seedParams);

        $processId   = (string) $this->restData($start)['process_id'];
        $finalStatus = BackgroundProcessDrainer::runSeedToCompletion($manager, 'lifterlms', $processId);

        $this->assertSame(
            SeedingStatus::STATUS_COMPLETED,
            $finalStatus->getStatus(),
            'Seeding failed: ' . ($finalStatus->getError() ?? $finalStatus->getStatus()),
        );
        $this->assertSame(
            LmsContentInspector::expectedQueueTotal('lifterlms', $seedParams),
            $finalStatus->getTotal(),
        );

        LmsContentInspector::assertSeededGroups(
            $this,
            'lifterlms',
            self::GROUPS,
            self::USERS,
            $maxPostId,
        );

        $this->assertGreaterThan($maxPostId, $this->maxPostId(), 'Expected new posts to be created.');
    }

    private function isLifterLmsActive(): bool
    {
        foreach (PluginTestAccessor::seedingManager()->getSupportedPlugins() as $plugin) {
            if ($plugin['slug'] === 'lifterlms' && $plugin['active']) {
                return true;
            }
        }

        return false;
    }

    private function maxPostId(): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
    }
}
