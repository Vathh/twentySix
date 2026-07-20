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
use App\Services\GroupStanding\GroupStandingService;
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

    public function test_closing_last_group_game_starts_playoff_for_standalone_tournament(): void
    {
        $tournament = Tournament::create([
            'name' => 'Standalone',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => \App\Enums\TournamentStatus::GROUP,
            'groups_count' => 2,
            'playoff_bracket_size' => 4,
            'group_advances' => [2, 2],
        ]);

        $players = collect(range(1, 6))->map(fn (int $i) => Player::create([
            'name' => "P{$i}",
            'season_id' => null,
            'league_id' => null,
        ]));

        foreach ($players as $index => $player) {
            GroupStanding::create([
                'tournament_id' => $tournament->id,
                'group_number' => $index < 3 ? 1 : 2,
                'player_id' => $player->id,
            ]);
        }

        $format = MatchFormat::default()->toDatabaseColumns();

        foreach ([
            [$players[0]->id, $players[1]->id, $players[0]->id, 1],
            [$players[0]->id, $players[2]->id, $players[0]->id, 1],
            [$players[1]->id, $players[2]->id, $players[1]->id, 1],
            [$players[3]->id, $players[4]->id, $players[3]->id, 2],
            [$players[3]->id, $players[5]->id, $players[3]->id, 2],
        ] as [$p1, $p2, $winner, $group]) {
            Game::create(array_merge([
                'tournament_id' => $tournament->id,
                'player1_id' => $p1,
                'player2_id' => $p2,
                'player1_score' => 2,
                'player2_score' => 0,
                'winner_id' => $winner,
                'group_number' => $group,
                'status' => GameStatus::FINISHED,
            ], $format));
        }

        $groupStandingService = app(GroupStandingService::class);
        $groupStandingService->recalculateGroupFromFinishedGames($tournament->id, 1);
        $groupStandingService->recalculateGroupFromFinishedGames($tournament->id, 2);

        $lastGame = Game::create(array_merge([
            'tournament_id' => $tournament->id,
            'player1_id' => $players[4]->id,
            'player2_id' => $players[5]->id,
            'player1_score' => 0,
            'player2_score' => 0,
            'winner_id' => null,
            'group_number' => 2,
            'status' => GameStatus::SCHEDULED,
        ], $format));

        app(GameResultCorrectionService::class)->applyWalkoverFromWeb(
            \App\Enums\GameKind::GROUP,
            $lastGame->id,
            $players[4]->id,
        );

        $tournament->refresh();
        $lastGame->refresh();

        $this->assertSame(GameStatus::FINISHED, $lastGame->status);
        $this->assertSame(\App\Enums\TournamentStatus::PLAYOFF, $tournament->status);
        $this->assertSame(4, \App\Models\PlayoffGame\PlayoffGame::where('tournament_id', $tournament->id)->count());
        $this->assertDatabaseCount('tournament_results', 2);
        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $players[2]->id,
            'place' => 5,
            'points' => null,
            'elimination_stage' => \App\Enums\GameStage::GROUP->value,
        ]);
        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $tournament->id,
            'player_id' => $players[5]->id,
            'place' => 5,
            'points' => null,
            'elimination_stage' => \App\Enums\GameStage::GROUP->value,
        ]);
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
