<?php

namespace Tests\Feature;

use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Models\League\League;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\PointScheme\PointScheme;
use App\Models\PointScheme\PointSchemeRule;
use App\Models\Season\Season;
use App\Models\Tournament\LoginCode;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Game\GameService;
use App\Services\PlayerService;
use App\Support\Tournament\PlayoffFirstRoundPairing;
use App\Support\Tournament\TournamentGroupDistribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private League $league;
    private Season $season;
    private Player $player1;
    private Player $player2;
    private Player $player3;
    private Player $player4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'can_create_leagues' => true,
        ]);

        $this->league = League::create(['name' => 'Test League', 'description' => 'Test']);
        $this->league->admins()->attach($this->adminUser->id);

        $this->season = Season::create([
            'name' => 'Test Season',
            'league_id' => $this->league->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $this->season->admins()->attach($this->adminUser->id);

        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $this->adminUser->id);

        $this->player1 = Player::where('user_id', $this->adminUser->id)->first();
        $this->player1->update(['season_id' => $this->season->id, 'league_id' => $this->league->id]);

        $this->player2 = Player::create([
            'name' => 'Player2',
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
        ]);
        $this->player3 = Player::create([
            'name' => 'Player3',
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
        ]);
        $this->player4 = Player::create([
            'name' => 'Player4',
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
        ]);

        $smallScheme = PointScheme::create([
            'name' => 'od 2 do 8 osob',
            'min_players' => 2,
            'max_players' => 8,
        ]);

        PointSchemeRule::insert([
            ['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 2, 'points' => 2],
            ['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 1, 'points' => 4],
            ['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 6],
            ['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::SEMI->value, 'place' => null, 'points' => 8],
            ['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 10],
            ['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 12],
        ]);
    }

    public function test_full_flow_start_groups_finish_triggers_playoff(): void
    {
        $this->actingAs($this->adminUser);

        $tournament = Tournament::create([
            'name' => 'Flow Test',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $playerIds = [$this->player1->id, $this->player2->id, $this->player3->id, $this->player4->id];

        $response = $this->post("/tournaments/{$tournament->id}/run", [
            'selectedPlayers' => json_encode($playerIds),
            'groupsCount' => 2,
            'advancePerGroup' => 2,
            'tabletsCount' => 3,
        ]);

        $response->assertRedirect("/tournaments/{$tournament->id}");
        $response->assertSessionHas('success');

        $tournament->refresh();
        $this->assertSame(TournamentStatus::GROUP, $tournament->status);
        $this->assertSame(2, $tournament->groups_count);
        $this->assertSame(2, $tournament->advance_per_group);
        $this->assertSame(3, $tournament->tablets_count);
        $this->assertSame(3, LoginCode::where('tournament_id', $tournament->id)->count());

        $expectedGroupSizes = TournamentGroupDistribution::groupSizes(4, 2);
        $actualGroupSizes = GroupStanding::where('tournament_id', $tournament->id)
            ->selectRaw('group_number, count(*) as cnt')
            ->groupBy('group_number')
            ->orderBy('group_number')
            ->pluck('cnt')
            ->all();

        $this->assertSame($expectedGroupSizes, $actualGroupSizes);
        $this->assertSame(2, Game::where('tournament_id', $tournament->id)->count());

        $gameService = app(GameService::class);

        foreach (Game::where('tournament_id', $tournament->id)->orderBy('id')->get() as $game) {
            $winnerId = $game->player1_id;
            $loserId = $game->player2_id;

            $updated = $gameService->update(new UpdateGameDTO(
                gameResultDTO: new GameResultDTO(
                    gameId: $game->id,
                    type: GameType::GROUP,
                    player1Id: $game->player1_id,
                    player2Id: $game->player2_id,
                    player1Score: 2,
                    player2Score: 0,
                    winnerId: $winnerId,
                    tournamentId: $tournament->id,
                    groupNumber: $game->group_number,
                ),
                achievementsDTOs: [],
            ));

            $this->assertTrue($updated, "Nie udało się zakończyć meczu grupowego #{$game->id}");
        }

        $tournament->refresh();
        $this->assertSame(TournamentStatus::PLAYOFF, $tournament->status);

        $this->assertSame(4, PlayoffGame::where('tournament_id', $tournament->id)->count());

        $groupByPlayer = GroupStanding::where('tournament_id', $tournament->id)
            ->pluck('group_number', 'player_id')
            ->all();

        $semiPairs = PlayoffGame::where('tournament_id', $tournament->id)
            ->where('round', GameStage::SEMI->value)
            ->get()
            ->map(fn (PlayoffGame $game) => [$game->player1_id, $game->player2_id])
            ->all();

        $this->assertCount(2, $semiPairs);
        $this->assertTrue(
            PlayoffFirstRoundPairing::pairsSatisfyGroupConstraint($semiPairs, $groupByPlayer),
        );

        $this->assertSame(0, Game::where('tournament_id', $tournament->id)
            ->where('status', '!=', GameStatus::FINISHED->value)
            ->count());
    }
}
