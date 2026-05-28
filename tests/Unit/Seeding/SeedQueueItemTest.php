<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Seeding;

use Tangible\Populater\Seeding\SeedQueueItem;

class SeedQueueItemTest extends \WPTestCase
{
    public function test_round_trip_array(): void
    {
        $item = new SeedQueueItem('lesson', ['course_index' => 1, 'index' => 2]);

        $this->assertSame(
            ['type' => 'lesson', 'data' => ['course_index' => 1, 'index' => 2]],
            $item->toArray(),
        );

        $restored = SeedQueueItem::fromArray($item->toArray());
        $this->assertSame('lesson', $restored->type);
        $this->assertSame(1, $restored->data['course_index']);
    }
}
