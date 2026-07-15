<?php

namespace Tests\Unit\GameScoring;

use App\Support\GameScoring\ScoringStateContract;
use PHPUnit\Framework\TestCase;

class ScoringStateContractTest extends TestCase
{
    public function test_enrich_h2h_adds_unified_fields(): void
    {
        $payload = [
            'game' => [
                'id' => 10,
                'kind' => 'group',
                'status' => 'in_progress',
                'tournamentId' => 3,
                'player1LegsWon' => 0,
                'player2LegsWon' => 0,
                'startingScore' => 501,
                'matchFormat' => [
                    'startingScore' => 501,
                    'legsToWinSet' => 2,
                    'setsToWinMatch' => 1,
                    'gameType' => 'x01',
                    'outRule' => 'double_out',
                ],
            ],
            'players' => [
                ['playerId' => 1, 'name' => 'A'],
                ['playerId' => 2, 'name' => 'B'],
            ],
            'currentLeg' => ['id' => 5, 'legNumber' => 1, 'open' => true],
            'visits' => [
                [
                    'playerId' => 1,
                    'score' => 60,
                    'dartsInVisit' => 3,
                    'bust' => false,
                    'closedLeg' => false,
                ],
            ],
            'legs' => [],
        ];

        $out = ScoringStateContract::enrichH2h($payload);

        $this->assertSame('h2h', $out['format']);
        $this->assertSame('tournament_group', $out['meta']['kind']);
        $this->assertSame(2, $out['meta']['matchFormat']['legsToWinSet']);
        $this->assertArrayNotHasKey('legsToWin', $out['meta']);
        $this->assertSame(1, $out['turn']['currentPlayerIndex']);
        $this->assertGreaterThan(0, $out['revision']);
    }

    public function test_enrich_ffa_adds_unified_fields(): void
    {
        $payload = [
            'session' => [
                'lobbyId' => 7,
                'status' => 'in_progress',
                'legsToWinSet' => 2,
                'startingScore' => 501,
                'currentLegNumber' => 1,
                'legOpenerIndex' => 0,
                'currentPlayerIndex' => 1,
                'stateVersion' => 4,
                'quickGameId' => null,
                'matchFormat' => [
                    'startingScore' => 501,
                    'legsToWinSet' => 2,
                    'setsToWinMatch' => 1,
                    'gameType' => 'x01',
                    'outRule' => 'double_out',
                ],
            ],
            'players' => [
                ['playerId' => 10, 'name' => 'Host'],
                ['playerId' => 20, 'name' => 'Friend'],
            ],
            'currentLeg' => ['legNumber' => 1, 'open' => true],
            'visits' => [],
            'game' => [
                'status' => 'in_progress',
                'matchFormat' => [
                    'startingScore' => 501,
                    'legsToWinSet' => 2,
                    'setsToWinMatch' => 1,
                    'gameType' => 'x01',
                    'outRule' => 'double_out',
                ],
            ],
        ];

        $out = ScoringStateContract::enrichFfa($payload);

        $this->assertSame('ffa', $out['format']);
        $this->assertSame('quick_ffa', $out['meta']['kind']);
        $this->assertSame(7, $out['meta']['lobbyId']);
        $this->assertSame(2, $out['meta']['matchFormat']['legsToWinSet']);
        $this->assertArrayNotHasKey('legsToWin', $out['meta']);
        $this->assertSame(1, $out['turn']['currentPlayerIndex']);
        $this->assertGreaterThan(0, $out['revision']);
    }
}
