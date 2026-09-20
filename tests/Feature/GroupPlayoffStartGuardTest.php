<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\BracketSide;
use App\Enums\GameKind;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameVisit;
use App\Models\GroupStanding\GroupStanding;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\Game\GameRepository;
use App\Services\Game\GameService;
use App\Services\GameScoring\GameResultCorrectionService;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsTournamentTablet;
use Tests\TestCase;

class GroupPlayoffStartGuardTest extends TestCase
{
    use ActsAsTournamentTablet;
    use RefreshDatabase;

    private User $user;

    private Tournament $tournament;

    private Player $p1;

    private Player $p2;

    private Player $p3;

    private Player $p4;

    private Game $gameA;

    private Game $gameB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['can_create_organizations' => true]);
        app(PlayerService::class)->create('Admin', $this->user->id);

        $organization = Organization::create(['name' => 'Org', 'description' => '']);
        $organization->admins()->attach($this->user->id);

        $season = Season::create([
            'name' => 'Sezon',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $this->tournament = Tournament::create([
            'name' => 'Playoff guard',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
            'format' => TournamentFormat::GroupsPlayoff,
            'groups_count' => 2,
            'playoff_bracket_size' => 2,
            'group_advances' => [1, 1],
            'tablets_count' => 2,
        ]);

        $this->p1 = Player::where('user_id', $this->user->id)->first();
        $this->p1->update(['season_id' => $season->id, 'organization_id' => $organization->id]);
        $this->p2 = Player::create(['name' => 'P2', 'season_id' => $season->id, 'organization_id' => $organization->id]);
        $this->p3 = Player::create(['name' => 'P3', 'season_id' => $season->id, 'organization_id' => $organization->id]);
        $this->p4 = Player::create(['name' => 'P4', 'season_id' => $season->id, 'organization_id' => $organization->id]);

        foreach ([$this->p1, $this->p2] as $player) {
            GroupStanding::create([
                'tournament_id' => $this->tournament->id,
                'group_number' => 1,
                'player_id' => $player->id,
            ]);
        }
        foreach ([$this->p3, $this->p4] as $player) {
            GroupStanding::create([
                'tournament_id' => $this->tournament->id,
                'group_number' => 2,
                'player_id' => $player->id,
            ]);
        }

        $this->gameA = Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->p1->id,
            'player2_id' => $this->p2->id,
            'group_number' => 1,
            'status' => GameStatus::IN_PROGRESS,
            'starting_score' => 101,
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
        ]);
        $this->gameB = Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->p3->id,
            'player2_id' => $this->p4->id,
            'group_number' => 2,
            'status' => GameStatus::IN_PROGRESS,
            'starting_score' => 101,
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
        ]);
    }

    public function test_in_progress_games_block_playoff_start(): void
    {
        $this->assertFalse(app(GameRepository::class)->checkIfPlayoffShouldBeStarted($this->tournament->id));

        $this->finishGroupGame($this->gameA, $this->p1->id, 2, 0);

        $this->gameA->refresh();
        $this->assertSame(GameStatus::FINISHED, $this->gameA->status);
        $this->assertSame(2, (int) $this->gameA->player1_score);
        $this->assertDatabaseCount('playoff_games', 0);
        $this->tournament->refresh();
        $this->assertSame(TournamentStatus::GROUP, $this->tournament->status);
    }

    public function test_last_finished_group_game_starts_playoff_once(): void
    {
        $this->finishGroupGame($this->gameA, $this->p1->id, 2, 0);
        $this->finishGroupGame($this->gameB, $this->p3->id, 2, 0);

        $this->assertSame(1, PlayoffGame::where('tournament_id', $this->tournament->id)->count());
        $this->tournament->refresh();
        $this->assertSame(TournamentStatus::PLAYOFF, $this->tournament->status);

        app(GameService::class)->tryStartPlayoff($this->tournament->id);

        $this->assertSame(1, PlayoffGame::where('tournament_id', $this->tournament->id)->count());
    }

    public function test_finishing_after_existing_playoff_saves_result_without_duplicate_error(): void
    {
        $this->insertExistingPlayoffSlot();

        $this->finishGroupGame($this->gameA, $this->p1->id, 2, 0);
        $this->gameA->refresh();
        $this->assertSame(GameStatus::FINISHED, $this->gameA->status);
        $this->assertSame(2, (int) $this->gameA->player1_score);

        $this->finishGroupGame($this->gameB, $this->p3->id, 2, 0);
        $this->gameB->refresh();
        $this->assertSame(GameStatus::FINISHED, $this->gameB->status);
        $this->assertSame(1, PlayoffGame::where('tournament_id', $this->tournament->id)->count());
    }

    public function test_walkover_after_existing_playoff_saves_group_game(): void
    {
        $this->insertExistingPlayoffSlot();

        app(GameResultCorrectionService::class)->applyWalkoverFromWeb(
            GameKind::GROUP,
            $this->gameA->id,
            $this->p1->id,
        );

        $this->gameA->refresh();
        $this->assertSame(GameStatus::FINISHED, $this->gameA->status);
        $this->assertSame(2, (int) $this->gameA->player1_score);
        $this->assertSame(0, (int) $this->gameA->player2_score);
        $this->assertDatabaseCount('playoff_games', 1);
    }

    public function test_scoring_match_win_does_not_start_playoff_while_other_game_in_progress(): void
    {
        $this->actAsTournamentTablet($this->tournament);

        $this->closeScoringLeg($this->gameA->id, $this->p1->id, $this->p2->id);
        $this->closeScoringLeg($this->gameA->id, $this->p1->id, $this->p2->id);

        $this->gameA->refresh();
        $this->assertSame(GameStatus::FINISHED, $this->gameA->status);
        $this->assertSame(2, (int) $this->gameA->player1_score);
        $this->assertDatabaseCount('playoff_games', 0);
        $this->tournament->refresh();
        $this->assertSame(TournamentStatus::GROUP, $this->tournament->status);
    }

    public function test_scoring_last_match_starts_playoff(): void
    {
        $this->actAsTournamentTablet($this->tournament);
        $this->finishGroupGame($this->gameA, $this->p1->id, 2, 0);

        $this->closeScoringLeg($this->gameB->id, $this->p3->id, $this->p4->id);
        $this->closeScoringLeg($this->gameB->id, $this->p3->id, $this->p4->id);

        $this->gameB->refresh();
        $this->assertSame(GameStatus::FINISHED, $this->gameB->status);
        $this->assertSame(1, PlayoffGame::where('tournament_id', $this->tournament->id)->count());
        $this->tournament->refresh();
        $this->assertSame(TournamentStatus::PLAYOFF, $this->tournament->status);
    }

    public function test_duplicate_checkout_visit_is_ignored_and_close_leg_still_finishes(): void
    {
        $this->actAsTournamentTablet($this->tournament);

        $start = $this->postJson("/api/group-games/{$this->gameA->id}/legs", [
            'player1DoubleTracked' => false,
            'player2DoubleTracked' => false,
        ]);
        $start->assertOk();
        $legId = $start->json('currentLeg.id');

        $checkout = [
            'playerId' => $this->p1->id,
            'score' => 101,
            'remainingBefore' => 101,
            'remainingAfter' => 0,
            'dartsInVisit' => 2,
            'closedLeg' => true,
            'bust' => false,
        ];

        $this->postJson("/api/group-games/{$this->gameA->id}/legs/{$legId}/visits", [
            ...$checkout,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/group-games/{$this->gameA->id}/legs/{$legId}/visits", [
            ...$checkout,
            'score' => 0,
            'remainingBefore' => 0,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk();

        $this->assertSame(1, GameVisit::where('game_leg_id', $legId)->where('is_voided', false)->count());

        $this->postJson("/api/group-games/{$this->gameA->id}/legs/{$legId}/close", [
            'winnerId' => $this->p1->id,
            'players' => [
                ['playerId' => $this->p1->id, 'doubleTracked' => false],
                ['playerId' => $this->p2->id, 'doubleTracked' => false],
            ],
        ])->assertOk();

        $this->gameA->refresh();
        $this->assertSame(1, (int) $this->gameA->player1_score);
        $this->assertNotNull(
            GameLeg::query()->where('game_id', $this->gameA->id)->whereNotNull('finished_at')->first(),
        );
    }

    private function finishGroupGame(Game $game, int $winnerId, int $p1Score, int $p2Score): void
    {
        $this->assertTrue(app(GameService::class)->update(new UpdateGameDTO(
            gameResultDTO: new GameResultDTO(
                gameId: $game->id,
                type: GameType::GROUP,
                player1Id: $game->player1_id,
                player2Id: $game->player2_id,
                player1Score: $p1Score,
                player2Score: $p2Score,
                winnerId: $winnerId,
                tournamentId: $this->tournament->id,
                groupNumber: $game->group_number,
            ),
            achievementsDTOs: [],
        )));
    }

    private function insertExistingPlayoffSlot(): void
    {
        $format = MatchFormat::default();
        PlayoffGame::create(array_merge([
            'tournament_id' => $this->tournament->id,
            'bracket_side' => BracketSide::Main,
            'round' => GameStage::FINAL->value,
            'slot' => 'QF_1',
            'player1_id' => $this->p1->id,
            'player2_id' => $this->p3->id,
        ], $format->toDatabaseColumns()));
    }

    private function closeScoringLeg(int $gameId, int $winnerId, int $loserId): void
    {
        $start = $this->postJson("/api/group-games/{$gameId}/legs", [
            'player1DoubleTracked' => false,
            'player2DoubleTracked' => false,
        ]);
        $start->assertOk();
        $legId = $start->json('currentLeg.id');

        $this->postJson("/api/group-games/{$gameId}/legs/{$legId}/visits", [
            'playerId' => $winnerId,
            'score' => 60,
            'remainingBefore' => 101,
            'remainingAfter' => 41,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/group-games/{$gameId}/legs/{$legId}/close", [
            'winnerId' => $winnerId,
            'players' => [
                ['playerId' => $winnerId, 'doubleTracked' => false],
                ['playerId' => $loserId, 'doubleTracked' => false],
            ],
        ])->assertOk();
    }
}
