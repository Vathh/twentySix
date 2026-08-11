<?php

namespace Tests\Unit\Tournament;

use App\Support\Tournament\PlayoffByePairing;
use PHPUnit\Framework\TestCase;

class PlayoffByePairingTest extends TestCase
{
    public function test_pairs_six_players_into_bracket_of_eight(): void
    {
        $pairs = PlayoffByePairing::pair([1, 2, 3, 4, 5, 6], 8);

        $this->assertCount(4, $pairs);

        $players = [];
        $byeSlots = 0;
        foreach ($pairs as [$a, $b]) {
            if ($a === null) {
                $byeSlots++;
            } else {
                $players[] = $a;
            }
            if ($b === null) {
                $byeSlots++;
            } else {
                $players[] = $b;
            }
        }

        $this->assertSame(2, $byeSlots);
        sort($players);
        $this->assertSame([1, 2, 3, 4, 5, 6], $players);
    }

    public function test_chunk_pairs_allows_bye_vs_bye(): void
    {
        $pairs = PlayoffByePairing::chunkPairs([1, null, null, null, 2, 3, null, 4]);

        $this->assertSame([1, null], $pairs[0]);
        $this->assertSame([null, null], $pairs[1]);
        $this->assertSame([2, 3], $pairs[2]);
        $this->assertSame([null, 4], $pairs[3]);
    }

    public function test_random_pool_can_produce_bye_vs_bye(): void
    {
        $sawByeBye = false;

        for ($i = 0; $i < 200; $i++) {
            $pairs = PlayoffByePairing::pair([1, 2, 3, 4], 8);
            foreach ($pairs as [$a, $b]) {
                if ($a === null && $b === null) {
                    $sawByeBye = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($sawByeBye, 'Przy 4 bye w puli losowej powinien czasem wypaść mecz bye vs bye');
    }

    public function test_next_power_of_two(): void
    {
        $this->assertSame(8, PlayoffByePairing::nextPowerOfTwo(5));
        $this->assertSame(8, PlayoffByePairing::nextPowerOfTwo(8));
        $this->assertSame(16, PlayoffByePairing::nextPowerOfTwo(9));
    }
}
