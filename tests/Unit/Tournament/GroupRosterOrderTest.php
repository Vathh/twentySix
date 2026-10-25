<?php

namespace Tests\Unit\Tournament;

use App\Support\Tournament\GroupRosterOrder;
use PHPUnit\Framework\TestCase;

class GroupRosterOrderTest extends TestCase
{
    public function test_orders_names_alphabetically_and_ignores_place(): void
    {
        $rows = [
            ['name' => 'QQQ', 'place' => 1],
            ['name' => 'aaa', 'place' => 4],
            ['name' => 'CCC', 'place' => 2],
            ['name' => 'III', 'place' => 3],
        ];

        $ordered = GroupRosterOrder::byName($rows, fn (array $row) => $row['name']);

        $this->assertSame(
            ['aaa', 'CCC', 'III', 'QQQ'],
            array_column($ordered, 'name'),
        );
    }
}
