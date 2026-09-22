<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameVisit;
use App\Models\GroupStanding\GroupStanding;
use App\Models\League\LeagueGame;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use App\Services\League\LeagueSeasonService;
use App\Services\League\LeagueService;
use App\Services\Player\PlayerService;
use App\Services\Tournament\TournamentGroupMatrixLiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionThreeDartAverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_snapshot_and_tournament_api_weight_darts_and_skip_walkovers(): void
    {
        $user = User::factory()->create();
        app(PlayerService::class)->create('Anna', $user->id);
        $anna = Player::where('user_id', $user->id)->first();

        $organization = Organization::create(['name' => 'Klub', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $tournament = Tournament::create([
            'name' => 'Średnie',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
        ]);
        $bartek = Player::create([
            'name' => 'Bartek',
            'season_id' => $season->id,
            'organization_id' => $organization->id,
        ]);
        $celina = Player::create([
            'name' => 'Celina',
            'season_id' => $season->id,
            'organization_id' => $organization->id,
        ]);

        $short = $this->groupGame($tournament, $anna, $bartek, GameStatus::FINISHED);
        $this->visit($short, $anna, 1, 100, 3, bust: false);
        $this->visit($short, $anna, 2, 26, 3, bust: true);
        $this->visit($short, $anna, 3, 180, 3, bust: false, voided: true);
        $this->visit($short, $bartek, 4, 40, 3, bust: false);

        $long = $this->groupGame($tournament, $anna, $celina, GameStatus::FINISHED);
        $this->visit($long, $anna, 1, 60, 3, bust: false);

        $walkover = $this->groupGame($tournament, $bartek, $celina, GameStatus::FINISHED);

        $playoff = PlayoffGame::create([
            'tournament_id' => $tournament->id,
            'round' => 'FINAL',
            'slot' => 'F1',
            'player1_id' => $anna->id,
            'player2_id' => $bartek->id,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $anna->id,
            'status' => GameStatus::FINISHED,
        ]);
        $playoffLeg = GameLeg::create([
            'playoff_game_id' => $playoff->id,
            'leg_number' => 1,
            'player1_score' => 90,
            'player2_score' => 0,
            'winner_id' => $anna->id,
            'finished_at' => now(),
        ]);
        $this->visitOnLeg($playoffLeg, $anna, 1, 90, 3, bust: false);

        foreach ([$anna, $bartek, $celina] as $index => $player) {
            GroupStanding::create([
                'tournament_id' => $tournament->id,
                'group_number' => 1,
                'player_id' => $player->id,
                'games_played' => 2,
                'games_won' => $index === 0 ? 2 : 0,
                'games_lost' => $index === 0 ? 0 : 2,
                'match_units_won' => 0,
                'match_units_lost' => 0,
                'points' => $index === 0 ? 2 : 0,
                'place' => $index + 1,
            ]);
        }

        TournamentResult::create([
            'season_id' => $season->id,
            'tournament_id' => $tournament->id,
            'player_id' => $anna->id,
            'points' => 3,
            'place' => 1,
        ]);

        $snapshot = app(TournamentGroupMatrixLiveService::class)->snapshot($tournament->id);
        $shortRow = collect($snapshot['games'])->firstWhere('id', $short->id);
        $walkoverRow = collect($snapshot['games'])->firstWhere('id', $walkover->id);

        $this->assertEquals(50.0, $shortRow['player1Average']);
        $this->assertEquals(40.0, $shortRow['player2Average']);
        $this->assertEquals(53.33, $shortRow['player1RunningAverage']);
        $this->assertNull($walkoverRow['player1Average']);
        $this->assertEquals(40.0, $walkoverRow['player1RunningAverage']);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/tournaments/'.$tournament->id)->assertOk();
        $annaStanding = collect($response->json('groups.0.standings'))->firstWhere('playerId', $anna->id);
        $shortApi = collect($response->json('groups.0.games'))->firstWhere('id', $short->id);
        $walkoverApi = collect($response->json('groups.0.games'))->firstWhere('id', $walkover->id);
        $final = collect($response->json('playoff.0.games'))->firstWhere('id', $playoff->id);

        $this->assertEquals(53.33, $annaStanding['average']);
        $this->assertEquals(50.0, $shortApi['player1Average']);
        $this->assertNull($walkoverApi['player1Average']);
        $this->assertEquals(90.0, $final['player1Average']);
        $this->assertEquals(62.5, $response->json('results.0.average'));
    }

    public function test_league_season_api_returns_match_and_season_averages(): void
    {
        $admin = User::factory()->create(['can_create_organizations' => true]);
        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $admin->id);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $playerService->create('Anna', $userA->id);
        $playerService->create('Bartek', $userB->id);
        $playerA = Player::query()->where('user_id', $userA->id)->first();
        $playerB = Player::query()->where('user_id', $userB->id)->first();

        $organization = Organization::create(['name' => 'Klub', 'description' => '']);
        $organization->admins()->attach($admin->id);
        $organization->relatedUsers()->attach([$userA->id, $userB->id]);

        $leagueService = app(LeagueService::class);
        $league = $leagueService->create($organization->id, 'Mini', null, [[
            'name' => 'Jedyna',
            'capacity' => 4,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
            'promoteDirect' => 0,
            'promotePlayoff' => 0,
        ]]);
        $league->relatedUsers()->attach([$userA->id, $userB->id]);
        $division = $league->divisions->first();
        $leagueService->assignPlayer($league->id, $division->id, $playerA->id);
        $leagueService->assignPlayer($league->id, $division->id, $playerB->id);

        $season = app(LeagueSeasonService::class)->create(
            $league->id,
            'Sezon 1',
            'deadline',
            1,
            now()->toDateString(),
            now()->addMonth()->toDateString(),
            null,
            true,
        );

        $played = LeagueGame::query()->where('league_season_id', $season->id)->first();
        $played->update([
            'status' => LeagueGameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $played->player1_id,
        ]);
        $this->visitLeague($played, $playerA, 1, 100, 3, bust: false);
        $this->visitLeague($played, $playerA, 2, 26, 3, bust: true);

        $second = LeagueGame::create([
            'league_season_id' => $season->id,
            'league_season_division_id' => $played->league_season_division_id,
            'purpose' => LeagueGamePurpose::REGULAR,
            'player1_id' => $playerA->id,
            'player2_id' => $playerB->id,
            'status' => LeagueGameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 1,
            'winner_id' => $playerA->id,
            'starting_score' => 501,
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
        ]);
        $this->visitLeague($second, $playerA, 1, 60, 3, bust: false);

        $walkover = LeagueGame::create([
            'league_season_id' => $season->id,
            'league_season_division_id' => $played->league_season_division_id,
            'purpose' => LeagueGamePurpose::REGULAR,
            'player1_id' => $playerA->id,
            'player2_id' => $playerB->id,
            'status' => LeagueGameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $playerA->id,
            'walkover_type' => 'single',
            'starting_score' => 501,
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
        ]);

        Sanctum::actingAs($userA);
        $response = $this->getJson('/api/league-seasons/'.$season->id)->assertOk();
        $anna = collect($response->json('divisions.0.standings'))->firstWhere('playerId', $playerA->id);
        $games = collect($response->json('divisions.0.games'));

        $this->assertEquals(53.33, $anna['average']);
        $this->assertEquals(50.0, $games->firstWhere('id', $played->id)['player1Average']);
        $this->assertEquals(60.0, $games->firstWhere('id', $second->id)['player1Average']);
        $this->assertNull($games->firstWhere('id', $walkover->id)['player1Average']);
    }

    private function groupGame(Tournament $tournament, Player $player1, Player $player2, GameStatus $status): Game
    {
        return Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'group_number' => 1,
            'status' => $status,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $player1->id,
        ]);
    }

    private function visit(
        Game $game,
        Player $player,
        int $number,
        int $score,
        int $darts,
        bool $bust,
        bool $voided = false,
    ): void {
        $leg = GameLeg::query()->where('game_id', $game->id)->first() ?? GameLeg::create([
            'game_id' => $game->id,
            'leg_number' => 1,
            'player1_score' => 0,
            'player2_score' => 0,
            'finished_at' => now(),
        ]);
        $this->visitOnLeg($leg, $player, $number, $score, $darts, $bust, $voided);
    }

    private function visitLeague(
        LeagueGame $game,
        Player $player,
        int $number,
        int $score,
        int $darts,
        bool $bust,
    ): void {
        $leg = GameLeg::query()->where('league_game_id', $game->id)->first() ?? GameLeg::create([
            'league_game_id' => $game->id,
            'leg_number' => 1,
            'player1_score' => 0,
            'player2_score' => 0,
            'finished_at' => now(),
        ]);
        $this->visitOnLeg($leg, $player, $number, $score, $darts, $bust);
    }

    private function visitOnLeg(
        GameLeg $leg,
        Player $player,
        int $number,
        int $score,
        int $darts,
        bool $bust,
        bool $voided = false,
    ): void {
        GameVisit::create([
            'game_leg_id' => $leg->id,
            'player_id' => $player->id,
            'visit_number' => $number,
            'score' => $score,
            'remaining_before' => 501,
            'remaining_after' => $bust ? 501 : 401,
            'darts_in_visit' => $darts,
            'closed_leg' => false,
            'bust' => $bust,
            'is_voided' => $voided,
            'client_visit_id' => 'avg-'.$leg->id.'-'.$player->id.'-'.$number,
        ]);
    }
}
