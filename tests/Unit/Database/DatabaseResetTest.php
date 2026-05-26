<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Database;

use Tangible\Populater\Database\DatabaseReset;
use Brain\Monkey\Functions;

class DatabaseResetTest extends \WPTestCase
{
    private DatabaseReset $reset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reset = new DatabaseReset();
    }

    public function test_reset_returns_false_when_not_confirmed(): void
    {
        $result = $this->reset->reset(confirmed: false);

        $this->assertFalse($result);
    }

    public function test_reset_drops_and_recreates_tables_when_confirmed(): void
    {
        global $wpdb;
        $wpdb = $this->createMock(\stdClass::class);

        Functions\when('dbDelta')->justReturn([]);
        Functions\when('wp_get_current_user')->justReturn(new \stdClass());
        Functions\when('get_option')->justReturn('1');

        // We just test it doesn't throw and calls the hook
        Functions\expect('do_action')
            ->with('tangible_populater_database_reset')
            ->once();

        // Use a partial mock to avoid actual DB operations
        $reset = $this->getMockBuilder(DatabaseReset::class)
            ->onlyMethods(['dropAllTables', 'reinstallWordPress'])
            ->getMock();

        $reset->expects($this->once())->method('dropAllTables');
        $reset->expects($this->once())->method('reinstallWordPress');

        $result = $reset->reset(confirmed: true);

        $this->assertTrue($result);
    }

    public function test_get_tables_returns_array(): void
    {
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';

            public function get_col(string $query): array
            {
                return ['wp_posts', 'wp_users', 'wp_options'];
            }
        };

        $tables = $this->reset->getTablesToReset();

        $this->assertIsArray($tables);
    }

    public function test_is_safe_environment_checks_env_constant(): void
    {
        $this->assertIsBool($this->reset->isSafeEnvironment());
    }
}
