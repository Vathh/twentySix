<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameStatus;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Models\Organization\Organization;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\GameScoring\GameResultCorrectionService;
use App\Services\GroupStanding\GroupStandingService;
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
            'organization_id' => null,
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

    public function test_http_walkover_on_group_game_first_to_two(): void
    {
        ['user' => $user, 'game' => $game] = $this->createOpenGroupGame(MatchFormat::default());

        $this->actingAs($user)
            ->post(route('games.result.update', ['type' => 'group', 'id' => $game->id]), [
                'walkover' => 1,
                'winner_id' => $game->player1_id,
            ])
            ->assertRedirect(route('games.show', ['type' => 'group', 'id' => $game->id]));

        $game->refresh();
        $this->assertSame(GameStatus::FINISHED, $game->status);
        $this->assertSame(2, $game->player1_score);
        $this->assertSame(0, $game->player2_score);
        $this->assertSame($game->player1_id, $game->winner_id);
    }

    public function test_http_walkover_on_playoff_first_to_four(): void
    {
        ['user' => $user, 'game' => $game] = $this->createOpenPlayoffGame(new MatchFormat(legsToWinSet: 4, setsToWinMatch: 1));

        $this->actingAs($user)
            ->post(route('games.result.update', ['type' => 'playoff', 'id' => $game->id]), [
                'walkover' => 1,
                'winner_id' => $game->player2_id,
            ])
            ->assertRedirect(route('games.show', ['type' => 'playoff', 'id' => $game->id]));

        $game->refresh();
        $this->assertSame(GameStatus::FINISHED, $game->status);
        $this->assertSame(0, $game->player1_score);
        $this->assertSame(4, $game->player2_score);
        $this->assertSame($game->player2_id, $game->winner_id);
    }

    public function test_walkover_without_winner_is_visible_on_the_match_page(): void
    {
        ['user' => $user, 'game' => $game] = $this->createOpenGroupGame(MatchFormat::default());
        $show = route('games.show', ['type' => 'group', 'id' => $game->id]);

        $this->actingAs($user)
            ->from($show)
            ->post(route('games.result.update', ['type' => 'group', 'id' => $game->id]), [
                'walkover' => 1,
            ])
            ->assertRedirect($show)
            ->assertSessionHasErrors('winner_id');

        $this->actingAs($user)
            ->get($show)
            ->assertOk()
            ->assertSee('Wybierz zwycięzcę walkovera.');
    }

    public function test_walkover_confirm_escapes_apostrophe_in_player_name(): void
    {
        ['user' => $user, 'game' => $game] = $this->createOpenGroupGame(MatchFormat::default(), "O'Brien");

        $html = $this->actingAs($user)
            ->get(route('games.show', ['type' => 'group', 'id' => $game->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-confirm=', $html);
        $this->assertStringContainsString('O&#039;Brien', $html);
        $this->assertStringNotContainsString('onclick="return confirm', $html);
    }

    private function createGroupGame(MatchFormat $format): Game
    {
        $user = User::factory()->create(['can_create_organizations' => true]);
        $organization = Organization::create(['name' => 'Organizacja', 'description' => '']);
        $organization->admins()->attach($user->id);
        $season = Season::create([
            'name' => 'Sezon',
            'organization_id' => $organization->id,
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

        $p1 = Player::create(['name' => 'A', 'season_id' => $season->id, 'organization_id' => $organization->id]);
        $p2 = Player::create(['name' => 'B', 'season_id' => $season->id, 'organization_id' => $organization->id]);

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

    /**
     * @return array{user: User, game: Game}
     */
    private function createOpenGroupGame(MatchFormat $format, string $player1Name = 'A'): array
    {
        $user = User::factory()->create(['can_create_organizations' => true]);
        $organization = Organization::create(['name' => 'Organizacja', 'description' => '']);
        $organization->admins()->attach($user->id);
        $season = Season::create([
            'name' => 'Sezon',
            'organization_id' => $organization->id,
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
        $tournament->admins()->attach($user->id);

        $p1 = Player::create(['name' => $player1Name, 'season_id' => $season->id, 'organization_id' => $organization->id]);
        $p2 = Player::create(['name' => 'B', 'season_id' => $season->id, 'organization_id' => $organization->id]);

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

        $game = Game::create(array_merge([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_score' => 0,
            'player2_score' => 0,
            'winner_id' => null,
            'group_number' => 1,
            'status' => GameStatus::IN_PROGRESS,
        ], $format->toDatabaseColumns()));

        return ['user' => $user, 'game' => $game];
    }

    /**
     * @return array{user: User, game: PlayoffGame}
     */
    private function createOpenPlayoffGame(MatchFormat $format): array
    {
        $user = User::factory()->create();
        $tournament = Tournament::create([
            'name' => 'Playoff',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => \App\Enums\TournamentStatus::PLAYOFF,
        ]);
        $tournament->admins()->attach($user->id);

        $p1 = Player::create(['name' => 'Ernest']);
        $p2 = Player::create(['name' => 'Marek']);
        $game = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'SF',
            'slot' => 'SF-0',
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_score' => 0,
            'player2_score' => 0,
            'status' => GameStatus::IN_PROGRESS,
        ], $format->toDatabaseColumns()));

        return ['user' => $user, 'game' => $game];
    }
}
