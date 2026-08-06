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
            $this->assertFalse($a === null && $b === null, 'Para nie może mieć dwóch bye');
        }

        $this->assertSame(2, $byeSlots);
        sort($players);
        $this->assertSame([1, 2, 3, 4, 5, 6], $players);
    }

    public function test_next_power_of_two(): void
    {
        $this->assertSame(8, PlayoffByePairing::nextPowerOfTwo(5));
        $this->assertSame(8, PlayoffByePairing::nextPowerOfTwo(8));
        $this->assertSame(16, PlayoffByePairing::nextPowerOfTwo(9));
    }
}
