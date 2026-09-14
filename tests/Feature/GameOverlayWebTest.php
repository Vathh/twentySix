<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\GameLeg;
use App\Models\Game\GameLegPlayerStat;
use App\Models\Game\GameVisit;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GameOverlayWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_can_view_overlay_for_in_progress_game(): void
    {
        $game = $this->playoffGame(GameStatus::IN_PROGRESS, 'Overlay Alice', 'Overlay Bob');

        $this->get(route('games.overlay', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Overlay Alice')
            ->assertSee('Overlay Bob')
            ->assertSee('Overlay Cup')
            ->assertSee('Drabinka wygranych')
            ->assertDontSee('WB R1')
            ->assertSee('aria-label="Otwiera"', false)
            ->assertSee('game-overlay-opener', false)
            ->assertSee('Lotki')
            ->assertSee('LEGI')
            ->assertSee('images/logotyp.svg', false)
            ->assertSee('images/napis.svg', false)
            ->assertSee('game-overlay-scorebug', false)
            ->assertDontSee('SETY')
            ->assertDontSee('Strona główna')
            ->assertDontSee('Zaloguj się')
            ->assertDontSee('Znajomi');
    }

    public function test_overlay_stays_on_finished_game_while_live_redirects(): void
    {
        $game = $this->playoffGame(
            GameStatus::FINISHED,
            'Finished Alice',
            'Finished Bob',
            player1Score: 2,
            player2Score: 0,
        );

        $this->get(route('games.overlay', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Finished Alice')
            ->assertSee('Finished Bob')
            ->assertSee('Koniec');

        $this->get(route('games.live', ['type' => 'playoff', 'id' => $game->id]))
            ->assertRedirect(route('games.show', ['type' => 'playoff', 'id' => $game->id]));
    }

    public function test_overlay_preview_and_solid_background_query_params(): void
    {
        $game = $this->playoffGame(GameStatus::IN_PROGRESS, 'Preview A', 'Preview B');

        $this->get(route('games.overlay', ['type' => 'playoff', 'id' => $game->id, 'preview' => 1]))
            ->assertOk()
            ->assertSee('game-overlay-preview', false)
            ->assertSee('is-throwing', false)
            ->assertSee('aria-label="Otwiera"', false);

        $this->get(route('games.overlay', ['type' => 'playoff', 'id' => $game->id, 'bg' => 'solid']))
            ->assertOk()
            ->assertSee('game-overlay-bg-solid', false);
    }

    public function test_show_and_live_pages_include_overlay_link_when_in_progress(): void
    {
        $game = $this->playoffGame(GameStatus::IN_PROGRESS, 'Link Alice', 'Link Bob');
        $overlayUrl = route('games.overlay', ['type' => 'playoff', 'id' => $game->id]);

        $this->get(route('games.show', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Kopiuj link overlay')
            ->assertSee($overlayUrl, false);

        $this->get(route('games.live', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Kopiuj link overlay')
            ->assertSee($overlayUrl, false);
    }

    public function test_overlay_shows_sets_and_legs_for_multi_set_format(): void
    {
        $format = new MatchFormat(legsToWinSet: 3, setsToWinMatch: 3);
        $game = $this->playoffGame(
            GameStatus::IN_PROGRESS,
            'Sets Alice',
            'Sets Bob',
            format: $format,
        );

        $this->get(route('games.overlay', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('SETY')
            ->assertSee('LEGI');
    }

    public function test_live_state_skips_full_detail_and_returns_410_when_finished(): void
    {
        $live = $this->playoffGame(GameStatus::IN_PROGRESS, 'State Alice', 'State Bob');

        $this->getJson(route('games.live.state', ['type' => 'playoff', 'id' => $live->id]))
            ->assertOk()
            ->assertJsonPath('game.status', 'in_progress')
            ->assertJsonPath('players.0.name', 'State Alice');

        $finished = $this->playoffGame(
            GameStatus::FINISHED,
            'Done Alice',
            'Done Bob',
            player1Score: 2,
            player2Score: 0,
        );

        $this->getJson(route('games.live.state', ['type' => 'playoff', 'id' => $finished->id]))
            ->assertStatus(410)
            ->assertJsonPath('message', 'Mecz zakończony.');
    }

    public function test_overlay_queries_game_visits_once(): void
    {
        $game = $this->playoffGame(GameStatus::IN_PROGRESS, 'Visit Alice', 'Visit Bob');
        $leg = GameLeg::create([
            'playoff_game_id' => $game->id,
            'leg_number' => 1,
            'started_at' => now(),
        ]);
        GameVisit::create([
            'game_leg_id' => $leg->id,
            'player_id' => $game->player1_id,
            'visit_number' => 1,
            'score' => 60,
            'remaining_before' => 501,
            'remaining_after' => 441,
            'darts_in_visit' => 3,
            'closed_leg' => false,
            'bust' => false,
            'is_voided' => false,
            'client_visit_id' => 'overlay-visit-once',
        ]);

        $visitSelects = 0;
        DB::listen(function ($query) use (&$visitSelects) {
            if (preg_match('/from [`"]?game_visits[`"]?/i', $query->sql) === 1) {
                $visitSelects++;
            }
        });

        $this->get(route('games.overlay', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Visit Alice');

        $this->assertSame(1, $visitSelects);
    }

    public function test_show_page_keeps_persisted_leg_average_on_closed_leg(): void
    {
        $game = $this->playoffGame(
            GameStatus::FINISHED,
            'Cache Alice',
            'Cache Bob',
            player1Score: 1,
            player2Score: 0,
        );
        $leg = GameLeg::create([
            'playoff_game_id' => $game->id,
            'leg_number' => 1,
            'winner_id' => $game->player1_id,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        GameLegPlayerStat::create([
            'game_leg_id' => $leg->id,
            'player_id' => $game->player1_id,
            'double_tracked' => false,
            'leg_average' => 77.77,
            'first_nine_average' => 77.77,
            'darts_thrown' => 9,
        ]);
        GameVisit::create([
            'game_leg_id' => $leg->id,
            'player_id' => $game->player1_id,
            'visit_number' => 1,
            'score' => 180,
            'remaining_before' => 501,
            'remaining_after' => 321,
            'darts_in_visit' => 3,
            'closed_leg' => false,
            'bust' => false,
            'is_voided' => false,
            'client_visit_id' => 'show-cache-avg',
        ]);

        $this->get(route('games.show', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('77.77');
    }

    public function test_show_page_formats_whole_leg_average_with_two_decimals(): void
    {
        $game = $this->playoffGame(
            GameStatus::FINISHED,
            'Whole Alice',
            'Whole Bob',
            player1Score: 1,
            player2Score: 0,
        );
        $leg = GameLeg::create([
            'playoff_game_id' => $game->id,
            'leg_number' => 1,
            'winner_id' => $game->player1_id,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        GameLegPlayerStat::create([
            'game_leg_id' => $leg->id,
            'player_id' => $game->player1_id,
            'double_tracked' => false,
            'leg_average' => 72,
            'first_nine_average' => 72,
            'darts_thrown' => 9,
        ]);

        $this->get(route('games.show', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('72.00');
    }

    private function playoffGame(
        GameStatus $status,
        string $player1Name,
        string $player2Name,
        int $player1Score = 0,
        int $player2Score = 0,
        ?MatchFormat $format = null,
    ): PlayoffGame {
        $tournament = Tournament::create([
            'name' => 'Overlay Cup',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'tournament_format' => 'double_elimination',
            'groups_count' => null,
            'playoff_bracket_size' => 4,
            'tablets_count' => 1,
        ]);

        $p1 = Player::create(['name' => $player1Name]);
        $p2 = Player::create(['name' => $player2Name]);
        $format = ($format ?? MatchFormat::default())->toDatabaseColumns();

        return PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'W0',
            'slot' => 'W0-0',
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'status' => $status,
            'player1_score' => $player1Score,
            'player2_score' => $player2Score,
        ], $format));
    }
}
