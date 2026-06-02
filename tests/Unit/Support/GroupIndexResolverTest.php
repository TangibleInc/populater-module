<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Support;

use Tangible\Populater\Support\GroupIndexResolver;

class GroupIndexResolverTest extends \WPTestCase
{
    public function test_returns_zero_when_groups_disabled(): void
    {
        $this->assertSame(0, GroupIndexResolver::resolve(1, 10, 0));
    }

    public function test_distributes_items_evenly(): void
    {
        $this->assertSame(1, GroupIndexResolver::resolve(1, 10, 2));
        $this->assertSame(1, GroupIndexResolver::resolve(5, 10, 2));
        $this->assertSame(2, GroupIndexResolver::resolve(6, 10, 2));
        $this->assertSame(2, GroupIndexResolver::resolve(10, 10, 2));
    }

    public function test_caps_groups_to_item_count(): void
    {
        $this->assertSame(1, GroupIndexResolver::resolve(1, 2, 5));
        $this->assertSame(2, GroupIndexResolver::resolve(2, 2, 5));
    }
}
