<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\CareerSource;
use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Career\PlayerGameSnapshot;
use App\Models\Game\Game;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerGameHistoryWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_labels_group_and_playoff_as_tournament(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);

        $playerService = app(PlayerService::class);
        $user = User::factory()->create();
        $playerService->create('Anna Nowak', $user->id);
        $player = Player::query()->where('user_id', $user->id)->firstOrFail();
        $opponent = Player::create(['name' => 'Opponent']);

        $tournament = Tournament::create([
            'name' => 'Puchar historii',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::FINISHED,
        ]);

        Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $player->id,
        ]);

        PlayoffGame::create(array_merge([
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

        $this->get(route('players.show', $player))
            ->assertOk()
            ->assertSee("if (type === 'group' || type === 'playoff') return 'Turniej';", false)
            ->assertDontSee("return 'Play-off'", false)
            ->assertDontSee("return 'Grupa'", false)
            ->assertSee('"type":"playoff"', false)
            ->assertSee('"type":"group"', false);
    }

    public function test_overview_average_keeps_two_decimals_for_whole_number(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);

        $playerService = app(PlayerService::class);
        $user = User::factory()->create();
        $playerService->create('Średnia 72', $user->id);
        $player = Player::query()->where('user_id', $user->id)->firstOrFail();

        PlayerGameSnapshot::query()->create([
            'player_id' => $player->id,
            'source' => CareerSource::Tournament,
            'game_type' => 'x01',
            'occurred_at' => now(),
            'metrics' => [
                'darts_thrown' => 3,
                'points' => 72,
            ],
        ]);

        $this->get(route('players.show', $player))
            ->assertOk()
            ->assertSee('72.00');
    }
}
