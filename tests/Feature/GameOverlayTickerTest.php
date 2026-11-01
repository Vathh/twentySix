<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Models\Tournament\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameOverlayTickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_ticker_lists_other_live_games_and_skips_the_streamed_one(): void
    {
        $tournament = $this->tournament('Ticker Cup');
        $other = $this->tournament('Inny turniej');
        $format = MatchFormat::default()->toDatabaseColumns();
        $sets = (new MatchFormat(setsToWinMatch: 3))->toDatabaseColumns();

        $streamed = $this->playoff($tournament, 'Stream Anna', 'Stream Bob', GameStatus::IN_PROGRESS, [
            'round' => 'W0',
            'slot' => 'W0-0',
            'player1_score' => 1,
            'player2_score' => 0,
        ], $format);
        $side = $this->playoff($tournament, 'Side Carla', 'Side Darek', GameStatus::IN_PROGRESS, [
            'round' => 'W0',
            'slot' => 'W0-1',
            'player1_score' => 1,
            'player2_score' => 0,
            'player1_legs_in_set' => 2,
            'player2_legs_in_set' => 1,
        ], $sets);
        $this->playoff($tournament, 'Done Ewa', 'Done Filip', GameStatus::FINISHED, [
            'round' => 'W0',
            'slot' => 'W0-2',
            'player1_score' => 2,
            'player2_score' => 0,
        ], $format);
        $this->playoff($tournament, 'Soon Gabi', 'Soon Henio', GameStatus::SCHEDULED, [
            'round' => 'W0',
            'slot' => 'W0-3',
        ], $format);
        $this->playoff($other, 'Foreign Kasia', 'Foreign Leon', GameStatus::IN_PROGRESS, [
            'round' => 'W0',
            'slot' => 'W0-0',
            'player1_score' => 1,
            'player2_score' => 1,
        ], $format);

        $early = $this->group($tournament, 'Early Ola', 'Early Piotr', GameStatus::IN_PROGRESS, [
            'group_number' => 1,
            'sequence' => 2,
            'player1_score' => 2,
            'player2_score' => 0,
        ], $format);
        $later = $this->group($tournament, 'Group Iza', 'Group Jan', GameStatus::IN_PROGRESS, [
            'group_number' => 2,
            'sequence' => 1,
            'player1_score' => 1,
            'player2_score' => 1,
        ], $format);

        $page = $this->get(route('games.overlay-ticker', ['type' => 'playoff', 'id' => $streamed->id]))
            ->assertOk();
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $page
            ->assertSee('Early Ola')
            ->assertSee('Group Iza')
            ->assertSee('Side Carla')
            ->assertSee('Gr. 1', false)
            ->assertSee('Gr. 2', false)
            ->assertSee('Wygrani · runda 1', false)
            ->assertSee('(2–1)', false)
            ->assertSee('ticker-bar', false)
            ->assertDontSee('Stream Anna')
            ->assertDontSee('Done Ewa')
            ->assertDontSee('Soon Gabi')
            ->assertDontSee('Foreign Kasia');

        $this->getJson(route('games.overlay-ticker.state', ['type' => 'playoff', 'id' => $streamed->id]))
            ->assertOk()
            ->assertJsonCount(3, 'items')
            ->assertJsonPath('items.0.key', 'group:'.$early->id)
            ->assertJsonPath('items.0.label', 'Gr. 1')
            ->assertJsonPath('items.0.score1', 2)
            ->assertJsonPath('items.0.score2', 0)
            ->assertJsonPath('items.0.setLegs1', null)
            ->assertJsonPath('items.1.key', 'group:'.$later->id)
            ->assertJsonPath('items.1.player1', 'Group Iza')
            ->assertJsonPath('items.1.score1', 1)
            ->assertJsonPath('items.1.score2', 1)
            ->assertJsonPath('items.2.key', 'playoff:'.$side->id)
            ->assertJsonPath('items.2.player2', 'Side Darek')
            ->assertJsonPath('items.2.score1', 1)
            ->assertJsonPath('items.2.setLegs1', 2)
            ->assertJsonPath('items.2.setLegs2', 1)
            ->assertJsonMissing(['player1' => 'Stream Anna']);
    }

    public function test_same_numeric_id_in_the_other_table_stays_on_the_ticker(): void
    {
        $tournament = $this->tournament('Kolizja id');
        $format = MatchFormat::default()->toDatabaseColumns();
        $playoff = $this->playoff($tournament, 'On Air Anna', 'On Air Bob', GameStatus::IN_PROGRESS, [
            'round' => 'QF',
            'slot' => 'QF-0',
            'player1_score' => 0,
            'player2_score' => 1,
        ], $format);
        $group = $this->group($tournament, 'Same Id Ala', 'Same Id Bartek', GameStatus::IN_PROGRESS, [
            'id' => $playoff->id,
            'group_number' => 3,
            'player1_score' => 1,
            'player2_score' => 0,
        ], $format);

        $this->getJson(route('games.overlay-ticker.state', ['type' => 'playoff', 'id' => $playoff->id]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.key', 'group:'.$group->id)
            ->assertJsonPath('items.0.player1', 'Same Id Ala');

        $this->getJson(route('games.overlay-ticker.state', ['type' => 'group', 'id' => $group->id]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.key', 'playoff:'.$playoff->id)
            ->assertJsonPath('items.0.player1', 'On Air Anna')
            ->assertJsonPath('items.0.label', 'QF');
    }

    public function test_ticker_is_empty_when_no_other_game_is_live(): void
    {
        $tournament = $this->tournament('Pusty pasek');
        $format = MatchFormat::default()->toDatabaseColumns();
        $streamed = $this->playoff($tournament, 'Solo Anna', 'Solo Bob', GameStatus::IN_PROGRESS, [
            'round' => 'FINAL',
            'slot' => 'F-0',
        ], $format);

        $this->get(route('games.overlay-ticker', ['type' => 'playoff', 'id' => $streamed->id]))
            ->assertOk()
            ->assertDontSee('Solo Anna')
            ->assertSee('ticker-bar', false)
            ->assertSee('hidden', false);

        $this->get(route('games.overlay-ticker', [
            'type' => 'playoff',
            'id' => $streamed->id,
            'preview' => 1,
        ]))
            ->assertOk()
            ->assertSee('Brak innych meczów na żywo')
            ->assertSee('game-overlay-preview', false);

        $this->get(route('games.overlay-ticker', [
            'type' => 'playoff',
            'id' => $streamed->id,
            'bg' => 'solid',
        ]))
            ->assertOk()
            ->assertSee('game-overlay-bg-solid', false);

        $this->getJson(route('games.overlay-ticker.state', ['type' => 'playoff', 'id' => $streamed->id]))
            ->assertOk()
            ->assertJsonPath('items', []);
    }

    public function test_show_page_offers_ticker_link_for_tournament_games_only(): void
    {
        $tournament = $this->tournament('Linki OBS');
        $format = MatchFormat::default()->toDatabaseColumns();
        $game = $this->group($tournament, 'Link Ala', 'Link Bartek', GameStatus::IN_PROGRESS, [
            'group_number' => 1,
        ], $format);
        $tickerUrl = route('games.overlay-ticker', ['type' => 'group', 'id' => $game->id]);

        $this->get(route('games.show', ['type' => 'group', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Kopiuj link paska')
            ->assertSee('Podgląd paska')
            ->assertSee($tickerUrl, false)
            ->assertSee('1920×56', false);

        $this->get(route('games.live', ['type' => 'group', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Kopiuj link paska')
            ->assertSee($tickerUrl, false);

        $home = Player::create(['name' => 'Quick Ala']);
        $away = Player::create(['name' => 'Quick Bartek']);
        $quick = QuickGame::create(array_merge([
            'player1_id' => $home->id,
            'player2_id' => $away->id,
            'player1_score' => 0,
            'player2_score' => 0,
            'status' => GameStatus::IN_PROGRESS,
        ], $format));

        $this->get(route('games.show', ['type' => 'quick', 'id' => $quick->id]))
            ->assertOk()
            ->assertSee('Kopiuj link overlay')
            ->assertDontSee('Kopiuj link paska');

        $this->get('/games/quick/'.$quick->id.'/overlay-ticker')->assertNotFound();
        $this->get('/games/playoff/999999/overlay-ticker')->assertNotFound();
    }

    private function tournament(string $name): Tournament
    {
        return Tournament::create([
            'name' => $name,
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'tournament_format' => 'double_elimination',
            'groups_count' => 2,
            'playoff_bracket_size' => 4,
            'tablets_count' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $format
     */
    private function playoff(
        Tournament $tournament,
        string $player1Name,
        string $player2Name,
        GameStatus $status,
        array $overrides,
        array $format,
    ): PlayoffGame {
        $player1 = Player::create(['name' => $player1Name]);
        $player2 = Player::create(['name' => $player2Name]);

        return PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'W0',
            'slot' => 'W0-0',
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'status' => $status,
            'player1_score' => 0,
            'player2_score' => 0,
        ], $format, $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $format
     */
    private function group(
        Tournament $tournament,
        string $player1Name,
        string $player2Name,
        GameStatus $status,
        array $overrides,
        array $format,
    ): Game {
        $player1 = Player::create(['name' => $player1Name]);
        $player2 = Player::create(['name' => $player2Name]);

        return Game::create(array_merge([
            'tournament_id' => $tournament->id,
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'group_number' => 1,
            'status' => $status,
            'player1_score' => 0,
            'player2_score' => 0,
        ], $format, $overrides));
    }
}
