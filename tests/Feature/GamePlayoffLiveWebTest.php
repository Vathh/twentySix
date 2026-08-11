<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePlayoffLiveWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_playoff_live_page_works_for_de_round(): void
    {
        $admin = User::factory()->create();
        $tournament = Tournament::create([
            'name' => 'DE Live',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'tournament_format' => 'double_elimination',
            'groups_count' => null,
            'playoff_bracket_size' => 4,
            'tablets_count' => 1,
        ]);
        $tournament->admins()->attach($admin->id);

        $p1 = Player::create(['name' => 'Alice']);
        $p2 = Player::create(['name' => 'Bob']);
        $format = MatchFormat::default()->toDatabaseColumns();

        $game = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'W0',
            'slot' => 'W0-0',
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'status' => GameStatus::IN_PROGRESS,
            'player1_score' => 0,
            'player2_score' => 0,
        ], $format));

        $this->actingAs($admin)
            ->get(route('games.live', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('Bob')
            ->assertSee('WB R1');
    }

    public function test_playoff_show_page_works_for_de_round(): void
    {
        $admin = User::factory()->create();
        $tournament = Tournament::create([
            'name' => 'DE Show',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'tournament_format' => 'double_elimination',
            'groups_count' => null,
            'playoff_bracket_size' => 4,
            'tablets_count' => 1,
        ]);
        $tournament->admins()->attach($admin->id);

        $p1 = Player::create(['name' => 'Carol']);
        $p2 = Player::create(['name' => 'Dave']);
        $format = MatchFormat::default()->toDatabaseColumns();

        $game = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'L0',
            'slot' => 'L0-0',
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
        ], $format));

        $this->actingAs($admin)
            ->get(route('games.show', ['type' => 'playoff', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Carol')
            ->assertSee('Dave')
            ->assertSee('LB R1');
    }
}
