<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameLegPlayerStat;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use App\ViewModels\TournamentDataViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentAchievementsQfFromScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_achievements_tab_shows_qf_from_leg_player_stats(): void
    {
        $user = User::factory()->create(['email' => 'qf-achievements@test.com']);
        app(PlayerService::class)->create('Jan', $user->id);

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
            'leg_average' => 100.0,
            'darts_thrown' => 12,
        ]);
        GameLegPlayerStat::create([
            'game_leg_id' => $leg->id,
            'player_id' => $player2->id,
            'double_tracked' => false,
            'leg_average' => 40.0,
            'darts_thrown' => 9,
        ]);

        $tournament->load('achievements.player');
        $rows = (new TournamentDataViewModel($tournament))->achievements();

        $this->assertTrue($rows->has($player1->id));
        $qfValues = collect($rows[$player1->id]['qf'])->pluck('value')->all();
        $this->assertSame([12], $qfValues);
        $this->assertArrayNotHasKey($player2->id, $rows->all());
    }
}
