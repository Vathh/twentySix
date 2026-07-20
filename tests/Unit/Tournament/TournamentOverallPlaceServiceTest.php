<?php

namespace Tests\Unit\Tournament;

use App\Enums\GameStage;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Services\Tournament\TournamentOverallPlaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentOverallPlaceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_infers_bracket_size_from_eight_final_round(): void
    {
        $tournament = Tournament::create([
            'name' => 'Legacy',
            'season_id' => null,
            'date' => '2024-06-01',
            'playoff_bracket_size' => null,
        ]);

        foreach (range(1, 8) as $index) {
            PlayoffGame::create([
                'tournament_id' => $tournament->id,
                'round' => GameStage::EIGHT,
                'slot' => 'EIGHT_'.$index,
                'status' => \App\Enums\GameStatus::SCHEDULED,
            ]);
        }

        $reflection = new \ReflectionClass(TournamentOverallPlaceService::class);
        $method = $reflection->getMethod('inferBracketSizeFromPlayoffGames');
        $method->setAccessible(true);

        $size = $method->invoke(app(TournamentOverallPlaceService::class), $tournament->id);

        $this->assertSame(16, $size);
    }
}
