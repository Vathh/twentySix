<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Models\League\League;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\GameScoring\GameResultCorrectionService;
use App\Support\GameScoring\MatchFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameResultCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_walkover_uses_legs_when_single_set_format(): void
    {
        $game = $this->createGroupGame(MatchFormat::default());

        app(GameResultCorrectionService::class)->applyWalkoverFromWeb(
            \App\Enums\GameKind::GROUP,
            $game->id,
            $game->player1_id,
        );

        $game->refresh();
        $this->assertSame(2, $game->player1_score);
        $this->assertSame(0, $game->player2_score);
        $this->assertSame(GameStatus::FINISHED, $game->status);
        $this->assertSame(0, $game->player1_legs_in_set);
        $this->assertSame(0, $game->player2_legs_in_set);
    }

    public function test_walkover_uses_sets_when_multi_set_format(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 2);
        $game = $this->createGroupGame($format);

        app(GameResultCorrectionService::class)->applyWalkoverFromWeb(
            \App\Enums\GameKind::GROUP,
            $game->id,
            $game->player2_id,
        );

        $game->refresh();
        $this->assertSame(0, $game->player1_score);
        $this->assertSame(2, $game->player2_score);
        $this->assertSame($game->player2_id, $game->winner_id);
    }

    public function test_manual_correction_accepts_valid_set_score(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 2);
        $game = $this->createGroupGame($format);

        app(GameResultCorrectionService::class)->applyFromWeb(
            \App\Enums\GameKind::GROUP,
            $game->id,
            2,
            1,
        );

        $game->refresh();
        $this->assertSame(2, $game->player1_score);
        $this->assertSame(1, $game->player2_score);
        $this->assertSame($game->player1_id, $game->winner_id);
    }

    private function createGroupGame(MatchFormat $format): Game
    {
        $user = User::factory()->create(['can_create_leagues' => true]);
        $league = League::create(['name' => 'Liga', 'description' => '']);
        $league->admins()->attach($user->id);
        $season = Season::create([
            'name' => 'Sezon',
            'league_id' => $league->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $season->admins()->attach($user->id);
        $tournament = Tournament::create([
            'name' => 'Turniej',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => \App\Enums\TournamentStatus::GROUP,
        ]);

        $p1 = Player::create(['name' => 'A', 'season_id' => $season->id, 'league_id' => $league->id]);
        $p2 = Player::create(['name' => 'B', 'season_id' => $season->id, 'league_id' => $league->id]);

        GroupStanding::create([
            'tournament_id' => $tournament->id,
            'group_number' => 1,
            'player_id' => $p1->id,
        ]);
        GroupStanding::create([
            'tournament_id' => $tournament->id,
            'group_number' => 1,
            'player_id' => $p2->id,
        ]);

        return Game::create(array_merge([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_score' => 1,
            'player2_score' => 0,
            'winner_id' => $p1->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
        ], $format->toDatabaseColumns()));
    }
}
