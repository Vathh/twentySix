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
use App\Models\League\League;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Models\QuickGame\QuickGameResult;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HeadToHeadTest extends TestCase
{
    use RefreshDatabase;

    private Player $anna;

    private Player $bartek;

    private Organization $organization;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $viewer = User::factory()->create();
        app(PlayerService::class)->create('Viewer', $viewer->id);
        Sanctum::actingAs($viewer);

        $this->anna = Player::create(['name' => 'Anna']);
        $this->bartek = Player::create(['name' => 'Bartek']);
        $this->organization = Organization::create(['name' => 'Klub 26', 'description' => '']);
        $this->tournament = Tournament::create([
            'name' => 'Puchar Wiosny',
            'organization_id' => $this->organization->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::FINISHED,
        ]);
    }

    public function test_record_counts_finished_meetings_and_recent_skips_the_open_page(): void
    {
        $this->leagueGame($this->bartek, $this->anna, $this->bartek, '2020-01-15 18:00:00');
        $this->leagueGame($this->anna, $this->bartek, $this->anna, '2020-02-01 18:00:00', LeagueGameStatus::VOIDED);

        $group = $this->groupGame($this->bartek, $this->anna, $this->anna, '2021-03-01 18:00:00', 0, 2);
        $this->leg(['game_id' => $group->id], 40, 12, 80, 15, $this->anna->id);

        $this->quickGame($this->bartek, $this->anna, $this->bartek, '2025-01-10 18:00:00');
        $this->quickGame($this->anna, $this->bartek, $this->bartek, '2025-02-10 18:00:00');
        $this->quickGame($this->anna, $this->bartek, $this->anna, '2025-03-10 18:00:00');
        $latestQuick = $this->quickGame($this->anna, $this->bartek, $this->anna, '2025-04-10 18:00:00', 2, 1);
        QuickGameResult::create([
            'quick_game_id' => $latestQuick->id,
            'player_id' => $this->anna->id,
            'score' => 2,
            'place' => 1,
            'average' => 90,
        ]);
        QuickGameResult::create([
            'quick_game_id' => $latestQuick->id,
            'player_id' => $this->bartek->id,
            'score' => 1,
            'place' => 2,
            'average' => 50,
        ]);

        $current = $this->playoffGame($this->anna, $this->bartek, $this->anna, '2026-01-15 18:00:00');
        $this->leg(['playoff_game_id' => $current->id], 70, 20, 60, 24, $this->anna->id);

        $third = Player::create(['name' => 'Celina']);
        $ffa = QuickGame::create([
            'player1_id' => $this->anna->id,
            'player2_id' => $this->bartek->id,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $this->anna->id,
            'status' => GameStatus::FINISHED,
        ]);
        foreach ([$this->anna, $this->bartek, $third] as $index => $player) {
            QuickGameResult::create([
                'quick_game_id' => $ffa->id,
                'player_id' => $player->id,
                'score' => 3 - $index,
                'place' => $index + 1,
                'average' => 70,
            ]);
        }

        $this->getJson('/api/games/playoff/'.$current->id)
            ->assertOk()
            ->assertJsonPath('headToHead.meetings', 7)
            ->assertJsonPath('headToHead.meetingsWord', 'spotkań')
            ->assertJsonPath('headToHead.player1Wins', 4)
            ->assertJsonPath('headToHead.player2Wins', 3)
            ->assertJsonPath('headToHead.player1Legs', 8)
            ->assertJsonPath('headToHead.player2Legs', 5)
            ->assertJsonPath('headToHead.recentHasMore', true)
            ->assertJsonPath('headToHead.streak.label', '3 zwycięstw z rzędu')
            ->assertJsonPath('headToHead.player1Average', 80)
            ->assertJsonPath('headToHead.player2Average', 50)
            ->assertJsonPath('headToHead.streak.side', 'player1')
            ->assertJsonPath('headToHead.streak.count', 3)
            ->assertJsonPath('headToHead.firstMeetingAt', '15.01.2020')
            ->assertJsonCount(5, 'headToHead.recent')
            ->assertJsonPath('headToHead.recent.0.kind', 'quick')
            ->assertJsonPath('headToHead.recent.0.id', $latestQuick->id)
            ->assertJsonPath('headToHead.recent.0.player1Score', 2)
            ->assertJsonPath('headToHead.recent.0.player2Score', 1)
            ->assertJsonPath('headToHead.recent.0.player1Average', 90)
            ->assertJsonPath('headToHead.recent.0.player2Average', 50)
            ->assertJsonPath('headToHead.recent.0.placeLabel', 'Szybka gra')
            ->assertJsonPath('headToHead.recent.4.kind', 'group')
            ->assertJsonPath('headToHead.recent.4.id', $group->id)
            ->assertJsonPath('headToHead.recent.4.player1Score', 2)
            ->assertJsonPath('headToHead.recent.4.player2Score', 0)
            ->assertJsonPath('headToHead.recent.4.winnerSide', 'player1')
            ->assertJsonPath('headToHead.recent.4.player1Average', 80)
            ->assertJsonPath('headToHead.recent.4.player2Average', 40)
            ->assertJsonPath('headToHead.recent.4.placeLabel', 'Turniej · Klub 26');

        $this->get(route('games.show', ['type' => 'playoff', 'id' => $current->id]))
            ->assertOk()
            ->assertSee('H2H')
            ->assertSee('Anna ma serię 3 zwycięstw z rzędu')
            ->assertSee('Załaduj więcej')
            ->assertSee('Pierwsze spotkanie: 15.01.2020')
            ->assertSee('Turniej · Klub 26')
            ->assertSee('Szybka gra')
            ->assertDontSee('Liga · Klub 26 · Liga Zimowa');

        $this->getJson('/api/games/playoff/'.$current->id.'/head-to-head?offset=5')
            ->assertOk()
            ->assertJsonPath('hasMore', false)
            ->assertJsonCount(1, 'recent')
            ->assertJsonPath('recent.0.kind', 'league')
            ->assertJsonPath('recent.0.placeLabel', 'Liga · Klub 26');

        $this->getJson(route('games.head-to-head', ['type' => 'playoff', 'id' => $current->id]).'?offset=5')
            ->assertOk()
            ->assertJsonPath('recent.0.kind', 'league');
    }

    public function test_live_page_shows_history_without_counting_the_current_leg(): void
    {
        $this->quickGame($this->anna, $this->bartek, $this->bartek, '2025-05-01 18:00:00', 0, 2);

        $live = PlayoffGame::create(array_merge([
            'tournament_id' => $this->tournament->id,
            'round' => 'SF',
            'slot' => 'SF-0',
            'player1_id' => $this->anna->id,
            'player2_id' => $this->bartek->id,
            'player1_score' => 0,
            'player2_score' => 0,
            'status' => GameStatus::IN_PROGRESS,
        ], MatchFormat::default()->toDatabaseColumns()));

        $this->getJson('/api/games/playoff/'.$live->id)
            ->assertOk()
            ->assertJsonPath('headToHead.meetings', 1)
            ->assertJsonPath('headToHead.meetingsWord', 'spotkanie')
            ->assertJsonPath('headToHead.player1Wins', 0)
            ->assertJsonPath('headToHead.player2Wins', 1)
            ->assertJsonPath('headToHead.player1Legs', 0)
            ->assertJsonPath('headToHead.player2Legs', 2)
            ->assertJsonPath('headToHead.recentHasMore', false)
            ->assertJsonPath('headToHead.streak', null)
            ->assertJsonCount(1, 'headToHead.recent');

        $this->get(route('games.live', ['type' => 'playoff', 'id' => $live->id]))
            ->assertOk()
            ->assertSee('H2H')
            ->assertSee('Szybka gra')
            ->assertDontSee('Załaduj więcej');
    }

    public function test_first_meeting_and_multi_player_quick_game_have_no_pair_history(): void
    {
        $only = $this->groupGame($this->anna, $this->bartek, $this->anna, '2026-02-01 18:00:00', 2, 0);

        $this->getJson('/api/games/group/'.$only->id)
            ->assertOk()
            ->assertJsonPath('headToHead.meetings', 1)
            ->assertJsonPath('headToHead.player1Wins', 1)
            ->assertJsonCount(0, 'headToHead.recent');

        $this->get(route('games.show', ['type' => 'group', 'id' => $only->id]))
            ->assertOk()
            ->assertSee('Brak wcześniejszych spotkań poza tym meczem.');

        $fresh = PlayoffGame::create(array_merge([
            'tournament_id' => $this->tournament->id,
            'round' => 'QF',
            'slot' => 'QF-0',
            'player1_id' => $this->anna->id,
            'player2_id' => $this->bartek->id,
            'player1_score' => 0,
            'player2_score' => 0,
            'status' => GameStatus::SCHEDULED,
        ], MatchFormat::default()->toDatabaseColumns()));

        $this->getJson('/api/games/playoff/'.$fresh->id)
            ->assertOk()
            ->assertJsonPath('headToHead.meetings', 1);

        $third = Player::create(['name' => 'Celina']);
        $ffa = QuickGame::create([
            'player1_id' => $this->anna->id,
            'player2_id' => $this->bartek->id,
            'status' => GameStatus::FINISHED,
            'winner_id' => $this->anna->id,
            'player1_score' => 3,
            'player2_score' => 1,
        ]);
        foreach ([$this->anna, $this->bartek, $third] as $index => $player) {
            QuickGameResult::create([
                'quick_game_id' => $ffa->id,
                'player_id' => $player->id,
                'score' => 1,
                'place' => $index + 1,
            ]);
        }

        $this->getJson('/api/games/quick/'.$ffa->id)
            ->assertOk()
            ->assertJsonPath('headToHead', null);
    }

    private function groupGame(
        Player $home,
        Player $away,
        Player $winner,
        string $finishedAt,
        int $homeScore,
        int $awayScore,
    ): Game {
        $game = Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $home->id,
            'player2_id' => $away->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
            'player1_score' => $homeScore,
            'player2_score' => $awayScore,
            'winner_id' => $winner->id,
        ]);
        $game->forceFill(['finished_at' => $finishedAt])->save();

        return $game;
    }

    private function playoffGame(Player $home, Player $away, Player $winner, string $finishedAt): PlayoffGame
    {
        $game = PlayoffGame::create(array_merge([
            'tournament_id' => $this->tournament->id,
            'round' => 'FINAL',
            'slot' => 'FINAL-0',
            'player1_id' => $home->id,
            'player2_id' => $away->id,
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $winner->id,
        ], MatchFormat::default()->toDatabaseColumns()));
        $game->forceFill(['finished_at' => $finishedAt])->save();

        return $game;
    }

    private function quickGame(
        Player $home,
        Player $away,
        Player $winner,
        string $playedAt,
        int $homeScore = 2,
        int $awayScore = 0,
    ): QuickGame {
        $game = QuickGame::create([
            'player1_id' => $home->id,
            'player2_id' => $away->id,
            'player1_score' => $homeScore,
            'player2_score' => $awayScore,
            'winner_id' => $winner->id,
            'status' => GameStatus::FINISHED,
        ]);
        QuickGame::query()->whereKey($game->id)->update([
            'updated_at' => $playedAt,
            'created_at' => $playedAt,
        ]);

        return $game->fresh();
    }

    private function leagueGame(
        Player $home,
        Player $away,
        Player $winner,
        string $finishedAt,
        LeagueGameStatus $status = LeagueGameStatus::FINISHED,
    ): LeagueGame {
        $league = League::create([
            'organization_id' => $this->organization->id,
            'name' => 'Liga Zimowa',
            'description' => '',
        ]);
        $season = LeagueSeason::create([
            'league_id' => $league->id,
            'name' => 'Sezon',
            'status' => LeagueSeasonStatus::IN_PROGRESS,
            'calendar_mode' => LeagueCalendarMode::DEADLINE,
            'rounds_each' => 1,
            'start_date' => '2020-01-01',
            'end_date' => '2020-12-01',
        ]);
        $game = LeagueGame::create([
            'league_season_id' => $season->id,
            'purpose' => LeagueGamePurpose::REGULAR,
            'player1_id' => $home->id,
            'player2_id' => $away->id,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $winner->id,
            'status' => $status,
            'starting_score' => 501,
            'game_type' => 'x01',
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
        ]);
        $game->forceFill(['finished_at' => $finishedAt])->save();

        return $game;
    }

    private function leg(
        array $owner,
        int $homeAverage,
        int $homeDarts,
        int $awayAverage,
        int $awayDarts,
        ?int $winnerId = null,
    ): void {
        GameLeg::create(array_merge($owner, [
            'leg_number' => 1,
            'winner_id' => $winnerId,
            'player1_score' => 0,
            'player2_score' => 0,
            'player1_average' => $homeAverage,
            'player2_average' => $awayAverage,
            'player1_darts_thrown' => $homeDarts,
            'player2_darts_thrown' => $awayDarts,
            'finished_at' => now(),
        ]));
    }
}
