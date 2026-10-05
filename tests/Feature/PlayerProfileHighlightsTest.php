<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameStatus;
use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameLegPlayerStat;
use App\Models\Game\GameVisit;
use App\Models\League\League;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerProfileHighlightsTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);
        $this->viewer = User::factory()->create();
        app(PlayerService::class)->create('Viewer', $this->viewer->id);
    }

    public function test_highlights_take_best_qf_and_hf_from_competition_not_quick_game(): void
    {
        $player = $this->registeredPlayer('Anna Nowak');
        $opponent = Player::create(['name' => 'Rywal']);
        $tournament = $this->tournament();

        $group = Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $player->id,
        ]);
        $groupLeg = $this->closedLeg(['game_id' => $group->id], 1, $player, $player, 15, 120);
        GameVisit::create([
            'game_leg_id' => $groupLeg->id,
            'player_id' => $player->id,
            'visit_number' => 1,
            'score' => 120,
            'remaining_before' => 120,
            'remaining_after' => 0,
            'darts_in_visit' => 3,
            'closed_leg' => true,
            'bust' => false,
            'is_voided' => false,
            'client_visit_id' => 'visit-hf-120',
        ]);
        $this->closedLeg(['game_id' => $group->id], 2, $player, $player, 21, 90);
        $this->closedLeg(['game_id' => $group->id], 3, $opponent, $player, 9, 160);

        $playoff = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'FINAL',
            'slot' => 'FINAL-0',
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 1,
            'winner_id' => $player->id,
        ], MatchFormat::default()->toDatabaseColumns()));
        $this->closedLeg(['playoff_game_id' => $playoff->id], 1, $player, $player, 11, 105);

        $leagueGame = $this->leagueGame($player, $opponent);
        $this->closedLeg(['league_game_id' => $leagueGame->id], 1, $player, $player, 12, 140);
        $this->closedLeg(['league_game_id' => $leagueGame->id], 2, $player, $player, 6, 170, finished: false);

        $quick = QuickGame::create([
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'status' => GameStatus::FINISHED,
            'starting_score' => 501,
            'game_type' => 'x01',
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $player->id,
        ]);
        $this->closedLeg(['quick_game_id' => $quick->id], 1, $player, $player, 9, 170);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/players/'.$player->id)
            ->assertOk()
            ->assertJsonPath('highlights.fastestQf', 11)
            ->assertJsonPath('highlights.highestHf', 140)
            ->assertJsonPath('tournamentStats.fastest_qf', 11)
            ->assertJsonPath('tournamentStats.highest_hf', 120);

        $this->get(route('players.show', $player))
            ->assertOk()
            ->assertSeeInOrder([
                'Najszybsza lotka',
                '11 lotek',
                'Najwyższy finish',
                '140',
            ], false);
    }

    public function test_qf_stops_at_twenty_darts_and_hf_starts_at_100(): void
    {
        $player = $this->registeredPlayer('Bez progu');
        $opponent = Player::create(['name' => 'Rywal']);
        $tournament = $this->tournament();
        $group = Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
            'player1_score' => 1,
            'player2_score' => 0,
            'winner_id' => $player->id,
        ]);
        $this->closedLeg(['game_id' => $group->id], 1, $player, $player, 18, 99);
        $this->closedLeg(['game_id' => $group->id], 2, $player, $player, 20, 100);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/players/'.$player->id)
            ->assertOk()
            ->assertJsonPath('highlights.fastestQf', 18)
            ->assertJsonPath('highlights.highestHf', 100);
    }

    public function test_profile_without_competition_legs_has_null_highlights(): void
    {
        $player = $this->registeredPlayer('Pusty');

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/players/'.$player->id)
            ->assertOk()
            ->assertJsonPath('highlights.fastestQf', null)
            ->assertJsonPath('highlights.highestHf', null);

        $this->get(route('players.show', $player))
            ->assertOk()
            ->assertSeeInOrder([
                'Najszybsza lotka',
                '–',
                'Najwyższy finish',
                '–',
            ], false);
    }

    private function registeredPlayer(string $name): Player
    {
        $user = User::factory()->create();
        app(PlayerService::class)->create($name, $user->id);

        return Player::query()->where('user_id', $user->id)->firstOrFail();
    }

    private function tournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Puchar',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::FINISHED,
        ]);
    }

    private function leagueGame(Player $player, Player $opponent): LeagueGame
    {
        $organization = Organization::create(['name' => 'Klub', 'description' => '']);
        $league = League::create([
            'organization_id' => $organization->id,
            'name' => 'Liga',
            'description' => '',
        ]);
        $season = LeagueSeason::create([
            'league_id' => $league->id,
            'name' => 'Sezon',
            'status' => LeagueSeasonStatus::IN_PROGRESS,
            'calendar_mode' => LeagueCalendarMode::DEADLINE,
            'rounds_each' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-01',
        ]);

        return LeagueGame::create([
            'league_season_id' => $season->id,
            'purpose' => LeagueGamePurpose::REGULAR,
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'status' => LeagueGameStatus::FINISHED,
            'starting_score' => 501,
            'game_type' => 'x01',
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
            'winner_id' => $player->id,
        ]);
    }

    /**
     * @param  array<string, int>  $owner
     */
    private function closedLeg(
        array $owner,
        int $legNumber,
        Player $winner,
        Player $subject,
        int $darts,
        ?int $finish,
        bool $finished = true,
    ): GameLeg {
        $leg = GameLeg::create(array_merge($owner, [
            'leg_number' => $legNumber,
            'winner_id' => $winner->id,
            'player1_score' => 501,
            'player2_score' => 0,
            'finished_at' => $finished ? now() : null,
        ]));

        GameLegPlayerStat::create([
            'game_leg_id' => $leg->id,
            'player_id' => $subject->id,
            'double_tracked' => false,
            'darts_thrown' => $darts,
            'highest_finish' => $finish,
        ]);

        return $leg;
    }
}
