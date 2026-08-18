<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameLegPlayerStat;
use App\Models\Game\GameVisit;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use App\Services\Player\PlayerStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerTournamentStatsFromScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_stats_use_leg_player_stats_and_visits(): void
    {
        $user = User::factory()->create(['email' => 'stats-scoring@test.com']);
        $playerService = app(PlayerService::class);
        $playerService->create('Jan Kowalski', $user->id);

        $organization = Organization::create(['name' => 'L', 'description' => '']);
        $season = Season::create([
            'name' => 'S',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $tournament = Tournament::create([
            'name' => 'T',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::FINISHED,
        ]);

        $player1 = Player::where('user_id', $user->id)->first();
        $player2 = Player::create([
            'name' => 'Opponent',
            'season_id' => $season->id,
            'organization_id' => $organization->id,
        ]);

        $game = Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $player1->id,
        ]);

        $leg = GameLeg::create([
            'game_id' => $game->id,
            'leg_number' => 1,
            'starting_score' => 501,
            'opener_player_id' => $player1->id,
            'winner_id' => $player1->id,
            'player1_score' => 501,
            'player2_score' => 0,
            'finished_at' => now(),
        ]);

        GameLegPlayerStat::create([
            'game_leg_id' => $leg->id,
            'player_id' => $player1->id,
            'double_tracked' => false,
            'leg_average' => 90.5,
            'darts_thrown' => 15,
            'highest_finish' => 120,
        ]);
        GameLegPlayerStat::create([
            'game_leg_id' => $leg->id,
            'player_id' => $player2->id,
            'double_tracked' => false,
            'leg_average' => 40.0,
            'darts_thrown' => 12,
        ]);

        GameVisit::create([
            'game_leg_id' => $leg->id,
            'player_id' => $player1->id,
            'visit_number' => 1,
            'score' => 180,
            'remaining_before' => 501,
            'remaining_after' => 321,
            'darts_in_visit' => 3,
            'closed_leg' => false,
            'bust' => false,
            'is_voided' => false,
            'client_visit_id' => 'visit-180',
        ]);
        GameVisit::create([
            'game_leg_id' => $leg->id,
            'player_id' => $player1->id,
            'visit_number' => 2,
            'score' => 120,
            'remaining_before' => 120,
            'remaining_after' => 0,
            'darts_in_visit' => 3,
            'closed_leg' => true,
            'bust' => false,
            'is_voided' => false,
            'client_visit_id' => 'visit-hf',
        ]);

        $stats = app(PlayerStatsService::class);
        $stats->recalculateAndSave($player1->id);
        $tournamentStats = $stats->getStoredTournamentStats($player1);

        $this->assertSame(1, $tournamentStats['games']);
        $this->assertSame(90.5, $tournamentStats['avg_three_darts']);
        $this->assertSame(1, $tournamentStats['count_max']);
        $this->assertSame(1, $tournamentStats['count_hf']);
        $this->assertSame(120, $tournamentStats['highest_hf']);
        $this->assertSame(1, $tournamentStats['count_qf']);
        $this->assertSame(15, $tournamentStats['fastest_qf']);
    }
}
