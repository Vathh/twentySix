<?php

namespace Tests\Unit\Tournament;

use App\Domain\Game\PlayoffBye;
use App\Support\Tournament\PlayoffByePairing;
use PHPUnit\Framework\TestCase;

class PlayoffByePairingTest extends TestCase
{
    public function test_pairs_six_players_into_bracket_of_eight(): void
    {
        $byeId = 99;
        $pairs = PlayoffByePairing::pair([1, 2, 3, 4, 5, 6], 8, $byeId);

        $this->assertCount(4, $pairs);

        $players = [];
        $byeSlots = 0;
        foreach ($pairs as [$a, $b]) {
            if ($a === $byeId) {
                $byeSlots++;
            } else {
                $players[] = $a;
            }
            if ($b === $byeId) {
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
        $pairs = PlayoffByePairing::chunkPairs([1, 99, 99, 99, 2, 3, 99, 4]);

        $this->assertSame([1, 99], $pairs[0]);
        $this->assertSame([99, 99], $pairs[1]);
        $this->assertSame([2, 3], $pairs[2]);
        $this->assertSame([99, 4], $pairs[3]);
    }

    public function test_random_pool_can_produce_bye_vs_bye(): void
    {
        $byeId = 99;
        $sawByeBye = false;

        for ($i = 0; $i < 200; $i++) {
            $pairs = PlayoffByePairing::pair([1, 2, 3, 4], 8, $byeId);
            foreach ($pairs as [$a, $b]) {
                if ($a === $byeId && $b === $byeId) {
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

    public function test_advance_winner_distinguishes_bye_from_tbd(): void
    {
        $byeId = 7;

        $this->assertNull(PlayoffBye::advanceWinnerId(1, null, $byeId));
        $this->assertNull(PlayoffBye::advanceWinnerId(null, null, $byeId));
        $this->assertNull(PlayoffBye::advanceWinnerId(1, 2, $byeId));
        $this->assertSame(1, PlayoffBye::advanceWinnerId(1, $byeId, $byeId));
        $this->assertSame(2, PlayoffBye::advanceWinnerId($byeId, 2, $byeId));
        $this->assertSame($byeId, PlayoffBye::advanceWinnerId($byeId, $byeId, $byeId));
    }
}
