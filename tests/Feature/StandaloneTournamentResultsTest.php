<?php

namespace Tests\Feature;

use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use App\Services\GroupStanding\GroupStandingService;
use App\Services\Tournament\TournamentResultService;
use App\Support\GameScoring\MatchFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandaloneTournamentResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_playoff_elimination_creates_result_without_points(): void
    {
        $tournament = Tournament::create([
            'name' => 'Standalone playoff',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'groups_count' => 2,
            'playoff_bracket_size' => 2,
            'group_advances' => [1, 1],
        ]);

        $players = collect(range(1, 2))->map(fn (int $i) => Player::create([
            'name' => "P{$i}",
            'season_id' => null,
            'league_id' => null,
        ]));

        app(TournamentResultService::class)->createForPlayoff(
            $tournament->id,
            $players[0]->id,
            GameStage::FINAL,
            2,
        );

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $players[0]->id,
            'place' => 2,
            'points' => null,
            'season_id' => null,
            'elimination_stage' => GameStage::FINAL->value,
        ]);
    }

    public function test_group_losers_can_be_backfilled_for_existing_standalone_tournament(): void
    {
        $tournament = Tournament::create([
            'name' => 'Standalone groups done',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'groups_count' => 2,
            'playoff_bracket_size' => 4,
            'group_advances' => [1, 1],
        ]);

        $players = collect(range(1, 4))->map(fn (int $i) => Player::create([
            'name' => "P{$i}",
            'season_id' => null,
            'league_id' => null,
        ]));

        foreach ($players as $index => $player) {
            GroupStanding::create([
                'tournament_id' => $tournament->id,
                'group_number' => $index < 2 ? 1 : 2,
                'player_id' => $player->id,
            ]);
        }

        $format = MatchFormat::default()->toDatabaseColumns();

        Game::create(array_merge([
            'tournament_id' => $tournament->id,
            'player1_id' => $players[0]->id,
            'player2_id' => $players[1]->id,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $players[0]->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
        ], $format));

        Game::create(array_merge([
            'tournament_id' => $tournament->id,
            'player1_id' => $players[2]->id,
            'player2_id' => $players[3]->id,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $players[2]->id,
            'group_number' => 2,
            'status' => GameStatus::FINISHED,
        ], $format));

        $groupStandingService = app(GroupStandingService::class);
        $groupStandingService->recalculateGroupFromFinishedGames($tournament->id, 1);
        $groupStandingService->recalculateGroupFromFinishedGames($tournament->id, 2);

        app(TournamentResultService::class)->createForGroupLosers($tournament->id);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $players[1]->id,
            'place' => 5,
            'points' => null,
            'elimination_stage' => GameStage::GROUP->value,
        ]);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $players[3]->id,
            'place' => 5,
            'points' => null,
            'elimination_stage' => GameStage::GROUP->value,
        ]);
    }
}
