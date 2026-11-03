<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Domain\Tournament\PlayoffConsolationSeeding;
use App\Enums\BracketSide;
use App\Enums\GameKind;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Repositories\Player\PlayerRepository;
use App\Services\Game\GameService;
use App\Services\GameScoring\GameResultCorrectionService;
use App\Services\Tournament\TournamentFinishService;
use DomainException;
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

    public function test_consolation_pair_is_created_when_its_groups_finish(): void
    {
        $tournament = $this->startedConsolationTournament(12, 4, 4);

        $this->finishGroup($tournament->id, 1);
        $this->finishGroup($tournament->id, 2);

        $tournament->refresh();
        $this->assertSame(TournamentStatus::GROUP, $tournament->status);
        $this->assertSame(2, $this->filledConsolationFirstRound($tournament->id)->count());
        $this->assertSame(2, $this->openConsolationFirstRound($tournament->id)->count());
        $this->assertSame(7, PlayoffGame::where('tournament_id', $tournament->id)
            ->where('bracket_side', BracketSide::Consolation)
            ->count());

        $this->finishGroup($tournament->id, 3);
        $this->assertSame(7, PlayoffGame::where('tournament_id', $tournament->id)
            ->where('bracket_side', BracketSide::Consolation)
            ->count());
        $this->assertGreaterThan(0, $this->openConsolationFirstRound($tournament->id)->count());

        $this->finishGroup($tournament->id, 4);
        $tournament->refresh();
        $this->assertSame(TournamentStatus::PLAYOFF, $tournament->status);
        $this->assertSame(0, $this->openConsolationFirstRound($tournament->id)->count());
        $this->assertSame(
            $this->consolationPairsFromStandings($tournament),
            $this->consolationPairsFromGames($tournament->id, GameStage::QUARTER),
        );
    }

    public function test_consolation_bye_advances_before_the_other_group_finishes(): void
    {
        $tournament = $this->startedConsolationTournament(10, 2, 4);
        $this->finishGroup($tournament->id, 1);

        $tournament->refresh();
        $this->assertSame(TournamentStatus::GROUP, $tournament->status);

        $third = GroupStanding::query()
            ->where('tournament_id', $tournament->id)
            ->where('group_number', 1)
            ->where('place', 3)
            ->firstOrFail();
        $byeId = app(PlayerRepository::class)->byePlayerId();

        $this->assertDatabaseHas('playoff_games', [
            'tournament_id' => $tournament->id,
            'bracket_side' => BracketSide::Consolation->value,
            'player1_id' => $third->player_id,
            'player2_id' => $byeId,
            'status' => GameStatus::FINISHED->value,
        ]);
        $this->assertTrue(
            PlayoffGame::where('tournament_id', $tournament->id)
                ->where('bracket_side', BracketSide::Consolation)
                ->where('round', GameStage::QUARTER->value)
                ->where(function ($query) {
                    $query->whereNull('player1_id')->orWhereNull('player2_id');
                })
                ->exists()
        );
    }

    public function test_scheduled_consolation_pair_follows_a_group_correction(): void
    {
        $tournament = $this->startedConsolationTournament(12, 4, 4);
        $this->finishGroup($tournament->id, 1);
        $this->finishGroup($tournament->id, 2);

        $before = $this->consolationPairsFromGames($tournament->id, GameStage::QUARTER);
        $groupGame = Game::where('tournament_id', $tournament->id)->where('group_number', 1)->firstOrFail();

        app(GameResultCorrectionService::class)->applyFromWeb(GameKind::GROUP, $groupGame->id, 0, 2);

        $after = $this->consolationPairsFromGames($tournament->id, GameStage::QUARTER);
        $this->assertNotSame($before, $after);
        $this->assertTrue(
            $this->filledConsolationFirstRound($tournament->id)
                ->every(fn (PlayoffGame $game) => $game->status === GameStatus::SCHEDULED)
        );
    }

    public function test_group_correction_is_blocked_when_consolation_match_has_started(): void
    {
        $tournament = $this->startedConsolationTournament(12, 4, 4);
        $this->finishGroup($tournament->id, 1);
        $this->finishGroup($tournament->id, 2);

        PlayoffGame::where('tournament_id', $tournament->id)
            ->where('bracket_side', BracketSide::Consolation)
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->update(['status' => GameStatus::IN_PROGRESS]);
        $groupGame = Game::where('tournament_id', $tournament->id)->where('group_number', 1)->firstOrFail();

        $this->expectException(DomainException::class);
        app(GameResultCorrectionService::class)->applyFromWeb(GameKind::GROUP, $groupGame->id, 0, 2);
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

    private function startedConsolationTournament(int $playerCount, int $groups, int $bracketSize): Tournament
    {
        $ctx = $this->makeSeasonTournament($playerCount);
        $this->actingAs($ctx['admin']);
        $this->post("/tournaments/{$ctx['tournament']->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => $groups,
            'playoffBracketSize' => $bracketSize,
            'hasConsolationBracket' => '1',
        ])->assertSessionHas('success');

        return $ctx['tournament']->refresh();
    }

    private function finishGroup(int $tournamentId, int $groupNumber): void
    {
        $games = Game::where('tournament_id', $tournamentId)
            ->where('group_number', $groupNumber)
            ->orderBy('id')
            ->get();

        foreach ($games as $game) {
            $this->finishGroupGame($game);
        }
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function consolationPairsFromStandings(Tournament $tournament): array
    {
        $advances = array_values($tournament->group_advances ?? []);
        $losers = GroupStanding::query()
            ->where('tournament_id', $tournament->id)
            ->where('place', '>', 0)
            ->get()
            ->filter(function (GroupStanding $standing) use ($advances) {
                $advance = (int) ($advances[$standing->group_number - 1] ?? 0);

                return $standing->place > $advance;
            })
            ->map(fn (GroupStanding $standing) => [
                'player_id' => $standing->player_id,
                'group_number' => $standing->group_number,
                'place' => $standing->place,
            ])
            ->values()
            ->all();

        return PlayoffConsolationSeeding::pair(
            $losers,
            (int) $tournament->consolation_bracket_size,
            app(PlayerRepository::class)->byePlayerId(),
        );
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    /**
     * @return \Illuminate\Support\Collection<int, PlayoffGame>
     */
    private function filledConsolationFirstRound(int $tournamentId)
    {
        return PlayoffGame::query()
            ->where('tournament_id', $tournamentId)
            ->where('bracket_side', BracketSide::Consolation)
            ->where('round', GameStage::QUARTER->value)
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PlayoffGame>
     */
    private function openConsolationFirstRound(int $tournamentId)
    {
        return PlayoffGame::query()
            ->where('tournament_id', $tournamentId)
            ->where('bracket_side', BracketSide::Consolation)
            ->where('round', GameStage::QUARTER->value)
            ->where(function ($query) {
                $query->whereNull('player1_id')->orWhereNull('player2_id');
            })
            ->get();
    }

    private function consolationPairsFromGames(int $tournamentId, GameStage $round): array
    {
        return PlayoffGame::query()
            ->where('tournament_id', $tournamentId)
            ->where('bracket_side', BracketSide::Consolation)
            ->where('round', $round->value)
            ->get()
            ->sortBy(function (PlayoffGame $game): int {
                $slot = (string) $game->slot;

                return (int) substr($slot, (int) strrpos($slot, '_') + 1);
            })
            ->map(fn (PlayoffGame $game) => [(int) $game->player1_id, (int) $game->player2_id])
            ->values()
            ->all();
    }

    private function finishAllGroupGames(int $tournamentId): void
    {
        foreach (Game::where('tournament_id', $tournamentId)->orderBy('id')->get() as $game) {
            $this->finishGroupGame($game);
        }
    }

    private function finishGroupGame(Game $game): void
    {
        if ($game->status === GameStatus::FINISHED) {
            return;
        }

        $this->assertTrue(app(GameService::class)->update(new UpdateGameDTO(
            gameResultDTO: new GameResultDTO(
                gameId: $game->id,
                type: GameType::GROUP,
                player1Id: $game->player1_id,
                player2Id: $game->player2_id,
                player1Score: 2,
                player2Score: 0,
                winnerId: $game->player1_id,
                tournamentId: $game->tournament_id,
                groupNumber: $game->group_number,
            ),
            achievementsDTOs: [],
        )));
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
