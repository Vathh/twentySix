<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\BracketSide;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Services\Game\GameService;
use App\Services\Tournament\TournamentFinishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsTournamentParticipants;
use Tests\TestCase;

class ConsolationPlayoffTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTournamentParticipants;

    public function test_run_stores_consolation_flag_and_size(): void
    {
        $ctx = $this->makeSeasonTournament(6);

        $this->actingAs($ctx['admin']);
        $response = $this->post("/tournaments/{$ctx['tournament']->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => 2,
            'playoffBracketSize' => 4,
            'hasConsolationBracket' => '1',
        ]);

        $response->assertRedirect("/tournaments/{$ctx['tournament']->id}");
        $ctx['tournament']->refresh();
        $this->assertTrue($ctx['tournament']->has_consolation_bracket);
        $this->assertSame(2, $ctx['tournament']->consolation_bracket_size);
        $this->assertDatabaseHas('tournament_match_formats', [
            'tournament_id' => $ctx['tournament']->id,
            'bracket_side' => BracketSide::Consolation->value,
            'stage' => GameStage::FINAL->value,
        ]);
    }

    public function test_run_rejects_consolation_when_fewer_than_two_remain(): void
    {
        $ctx = $this->makeSeasonTournament(8);

        $this->actingAs($ctx['admin']);
        $response = $this->from("/tournaments/{$ctx['tournament']->id}/start")
            ->post("/tournaments/{$ctx['tournament']->id}/run", [
                'tournamentFormat' => 'groups_playoff',
                'groupsCount' => 2,
                'playoffBracketSize' => 8,
                'hasConsolationBracket' => '1',
            ]);

        $response->assertSessionHasErrors('hasConsolationBracket');
        $ctx['tournament']->refresh();
        $this->assertFalse((bool) $ctx['tournament']->has_consolation_bracket);
    }

    public function test_last_group_game_creates_both_brackets_without_group_loser_results(): void
    {
        $ctx = $this->makeSeasonTournament(6);
        $this->actingAs($ctx['admin']);
        $this->post("/tournaments/{$ctx['tournament']->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => 2,
            'playoffBracketSize' => 4,
            'hasConsolationBracket' => '1',
        ])->assertSessionHas('success');

        $this->finishAllGroupGames($ctx['tournament']->id);

        $ctx['tournament']->refresh();
        $this->assertSame(TournamentStatus::PLAYOFF, $ctx['tournament']->status);

        $this->assertSame(4, PlayoffGame::where('tournament_id', $ctx['tournament']->id)
            ->where('bracket_side', BracketSide::Main)
            ->count());
        $this->assertSame(1, PlayoffGame::where('tournament_id', $ctx['tournament']->id)
            ->where('bracket_side', BracketSide::Consolation)
            ->count());
        $this->assertDatabaseHas('playoff_games', [
            'tournament_id' => $ctx['tournament']->id,
            'slot' => 'C_FINAL',
            'bracket_side' => BracketSide::Consolation->value,
        ]);
        $this->assertSame(0, TournamentResult::where('tournament_id', $ctx['tournament']->id)
            ->where('elimination_stage', GameStage::GROUP)
            ->count());
    }

    public function test_consolation_final_assigns_offset_places_and_blocks_finish(): void
    {
        $ctx = $this->makeSeasonTournament(6);
        $this->actingAs($ctx['admin']);
        $this->post("/tournaments/{$ctx['tournament']->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => 2,
            'playoffBracketSize' => 4,
            'hasConsolationBracket' => '1',
        ])->assertSessionHas('success');

        $this->finishAllGroupGames($ctx['tournament']->id);

        $consolationFinal = PlayoffGame::where('tournament_id', $ctx['tournament']->id)
            ->where('slot', 'C_FINAL')
            ->firstOrFail();
        $this->assertNotNull($consolationFinal->player1_id);
        $this->assertNotNull($consolationFinal->player2_id);

        for ($pass = 0; $pass < 4; $pass++) {
            foreach (PlayoffGame::where('tournament_id', $ctx['tournament']->id)
                ->where('bracket_side', BracketSide::Main)
                ->orderBy('id')
                ->get() as $game) {
                $this->finishPlayoffIfReady($game);
            }
        }

        $this->assertFalse(app(TournamentFinishService::class)->tryFinish($ctx['tournament']->id));

        $consolationFinal->refresh();
        $this->finishPlayoffIfReady($consolationFinal);
        $consolationFinal->refresh();

        $this->assertSame(GameStatus::FINISHED, $consolationFinal->status);

        $winnerPlace = TournamentResult::where('tournament_id', $ctx['tournament']->id)
            ->where('player_id', $consolationFinal->winner_id)
            ->value('place');
        $this->assertSame(5, $winnerPlace);

        $loserId = $consolationFinal->winner_id === $consolationFinal->player1_id
            ? $consolationFinal->player2_id
            : $consolationFinal->player1_id;
        $this->assertSame(6, TournamentResult::where('tournament_id', $ctx['tournament']->id)
            ->where('player_id', $loserId)
            ->value('place'));

        $ctx['tournament']->refresh();
        $this->assertSame(TournamentStatus::FINISHED, $ctx['tournament']->status);
    }

    /**
     * @return array{admin: \App\Models\Users\User, tournament: Tournament}
     */
    private function makeSeasonTournament(int $playerCount): array
    {
        $admin = \App\Models\Users\User::factory()->create(['can_create_organizations' => true]);
        $organization = \App\Models\Organization\Organization::create(['name' => 'Org', 'description' => '']);
        $organization->admins()->attach($admin->id);
        $season = \App\Models\Season\Season::create([
            'name' => 'Sezon',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $season->admins()->attach($admin->id);

        $players = [];
        for ($i = 1; $i <= $playerCount; $i++) {
            $players[] = \App\Models\Player\Player::create([
                'name' => "G{$i}",
                'season_id' => $season->id,
                'organization_id' => $organization->id,
            ]);
        }

        $tournament = Tournament::create([
            'name' => 'Consolation',
            'season_id' => $season->id,
            'date' => '2024-06-01',
        ]);
        $this->addPlayersToTournamentPool($tournament, $players, $admin);

        return ['admin' => $admin, 'tournament' => $tournament];
    }

    private function finishAllGroupGames(int $tournamentId): void
    {
        $gameService = app(GameService::class);
        foreach (Game::where('tournament_id', $tournamentId)->orderBy('id')->get() as $game) {
            $this->assertTrue($gameService->update(new UpdateGameDTO(
                gameResultDTO: new GameResultDTO(
                    gameId: $game->id,
                    type: GameType::GROUP,
                    player1Id: $game->player1_id,
                    player2Id: $game->player2_id,
                    player1Score: 2,
                    player2Score: 0,
                    winnerId: $game->player1_id,
                    tournamentId: $tournamentId,
                    groupNumber: $game->group_number,
                ),
                achievementsDTOs: [],
            )));
        }
    }

    private function finishPlayoffIfReady(PlayoffGame $game): void
    {
        $game->refresh();
        if ($game->player1_id === null || $game->player2_id === null) {
            return;
        }
        if ($game->status === GameStatus::FINISHED) {
            return;
        }

        $format = MatchFormat::default();
        $this->assertTrue(
            app(GameService::class)->update(new UpdateGameDTO(
                gameResultDTO: new GameResultDTO(
                    gameId: $game->id,
                    type: GameType::PLAYOFF,
                    player1Id: $game->player1_id,
                    player2Id: $game->player2_id,
                    player1Score: $format->legsToWinSet,
                    player2Score: 0,
                    winnerId: $game->player1_id,
                    tournamentId: $game->tournament_id,
                ),
                achievementsDTOs: [],
            )),
            "Nie udało się zakończyć playoff #{$game->id} {$game->slot}",
        );
    }
}
