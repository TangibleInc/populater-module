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

    public function test_reset_cleans_content_when_confirmed(): void
    {
        Functions\when('get_users')->justReturn([1]);
        Functions\when('wp_insert_term')->justReturn(['term_id' => 1]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('delete_option')->justReturn(true);
        Functions\when('wp_cache_flush')->justReturn(true);
        Functions\when('delete_expired_transients')->justReturn(true);

        Functions\expect('do_action')
            ->with('tangible_populater_database_reset')
            ->once();

        $reset = $this->getMockBuilder(DatabaseReset::class)
            ->onlyMethods([
                'deletePostsAndComments',
                'resetTaxonomies',
                'deleteNonAdminUsers',
                'cleanupOptions',
                'resetSiteRoles',
                'restorePreservedAdministrators',
                'truncateCustomTables',
                'finalizeSiteState',
            ])
            ->getMock();

        $reset->expects($this->once())->method('deletePostsAndComments');
        $reset->expects($this->once())->method('resetTaxonomies');
        $reset->expects($this->once())->method('deleteNonAdminUsers')->with([1]);
        $reset->expects($this->once())->method('cleanupOptions');
        $reset->expects($this->once())->method('resetSiteRoles');
        $reset->expects($this->once())->method('restorePreservedAdministrators')->with([1]);
        $reset->expects($this->once())->method('truncateCustomTables');
        $reset->expects($this->once())->method('finalizeSiteState');

        $result = $reset->reset(confirmed: true);

        $this->assertTrue($result);
    }

    public function test_reset_aborts_when_no_administrators_exist(): void
    {
        Functions\when('get_users')->justReturn([]);

        $reset = $this->getMockBuilder(DatabaseReset::class)
            ->onlyMethods(['deletePostsAndComments'])
            ->getMock();

        $reset->expects($this->never())->method('deletePostsAndComments');

        $this->assertFalse($reset->reset(confirmed: true));
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

    public function test_is_safe_environment_allows_local_env_type(): void
    {
        Functions\when('wp_get_environment_type')->justReturn('local');

        $this->assertTrue($this->reset->isSafeEnvironment());
    }

    public function test_is_safe_environment_allows_wp_debug(): void
    {
        Functions\when('wp_get_environment_type')->justReturn('production');

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        $this->assertTrue($this->reset->isSafeEnvironment());
    }

    public function test_get_administrator_user_ids_merges_role_and_capability_queries(): void
    {
        Functions\when('get_users')->alias(function (array $args): array {
            if (($args['role'] ?? '') === 'administrator') {
                return [1, 3];
            }

            if (($args['capability'] ?? '') === 'manage_options') {
                return [3, 5];
            }

            return [];
        });

        $this->assertSame([1, 3, 5], $this->reset->getAdministratorUserIds());
    }

    public function test_reset_preserves_all_administrators(): void
    {
        Functions\when('wp_get_environment_type')->justReturn('local');
        Functions\when('get_users')->justReturn([1, 42]);
        Functions\when('wp_insert_term')->justReturn(['term_id' => 1]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('delete_option')->justReturn(true);
        Functions\when('wp_cache_flush')->justReturn(true);
        Functions\when('delete_expired_transients')->justReturn(true);

        Functions\expect('do_action')
            ->with('tangible_populater_database_reset')
            ->once();

        $reset = $this->getMockBuilder(DatabaseReset::class)
            ->onlyMethods([
                'deletePostsAndComments',
                'resetTaxonomies',
                'deleteNonAdminUsers',
                'cleanupOptions',
                'resetSiteRoles',
                'restorePreservedAdministrators',
                'truncateCustomTables',
                'finalizeSiteState',
            ])
            ->getMock();

        $reset->expects($this->once())->method('deleteNonAdminUsers')->with([1, 42]);
        $reset->expects($this->once())->method('restorePreservedAdministrators')->with([1, 42]);

        $this->assertTrue($reset->reset(confirmed: true));
    }
}
