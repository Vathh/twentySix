<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentGroupMatrixUpdated;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use App\Services\Tournament\TournamentGroupMatrixLiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TournamentGroupMatrixLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_from_group_game_broadcasts_score_without_standings_by_default(): void
    {
        Event::fake([TournamentGroupMatrixUpdated::class]);

        [$tournament, $game] = $this->seedGroupGame();

        app(TournamentGroupMatrixLiveService::class)->pushFromGroupGame($game, false);

        Event::assertDispatched(TournamentGroupMatrixUpdated::class, function (TournamentGroupMatrixUpdated $event) use ($tournament, $game) {
            return $event->tournamentId === $tournament->id
                && $event->payload['game']['id'] === $game->id
                && $event->payload['includeStandings'] === false
                && $event->payload['standings'] === null;
        });
    }

    public function test_snapshot_includes_games_and_standings(): void
    {
        [$tournament, $game] = $this->seedGroupGame();

        GroupStanding::create([
            'tournament_id' => $tournament->id,
            'group_number' => 1,
            'player_id' => $game->player1_id,
            'games_played' => 0,
            'games_won' => 0,
            'games_lost' => 0,
            'match_units_won' => 0,
            'match_units_lost' => 0,
            'points' => 0,
            'place' => 1,
        ]);
        GroupStanding::create([
            'tournament_id' => $tournament->id,
            'group_number' => 1,
            'player_id' => $game->player2_id,
            'games_played' => 0,
            'games_won' => 0,
            'games_lost' => 0,
            'match_units_won' => 0,
            'match_units_lost' => 0,
            'points' => 0,
            'place' => 2,
        ]);

        $snapshot = app(TournamentGroupMatrixLiveService::class)->snapshot($tournament->id);

        $this->assertSame($tournament->id, $snapshot['tournamentId']);
        $this->assertCount(1, $snapshot['games']);
        $this->assertSame($game->id, $snapshot['games'][0]['id']);
        $this->assertArrayHasKey(1, $snapshot['standingsByGroup']);
        $this->assertCount(2, $snapshot['standingsByGroup'][1]);
    }

    public function test_groups_live_endpoint_returns_json(): void
    {
        [$tournament] = $this->seedGroupGame();

        $this->getJson(route('tournaments.groups-live', $tournament))
            ->assertOk()
            ->assertJsonPath('tournamentId', $tournament->id);
    }

    /**
     * @return array{0: Tournament, 1: Game}
     */
    private function seedGroupGame(): array
    {
        $user = User::factory()->create(['email' => 'groups-live@test.com']);
        app(PlayerService::class)->create('Host', $user->id);

        $organization = Organization::create(['name' => 'L', 'description' => '']);
        $season = Season::create([
            'name' => 'S',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $tournament = Tournament::create([
            'name' => 'Live Groups',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
        ]);

        $p1 = Player::where('user_id', $user->id)->first();
        $p2 = Player::create([
            'name' => 'P2',
            'season_id' => $season->id,
            'organization_id' => $organization->id,
        ]);

        $game = Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'group_number' => 1,
            'status' => GameStatus::IN_PROGRESS,
            'player1_score' => 1,
            'player2_score' => 0,
        ]);

        return [$tournament, $game];
    }
}
