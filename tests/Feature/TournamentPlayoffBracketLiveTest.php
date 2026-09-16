<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentPlayoffBracketUpdated;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use App\Services\Tournament\TournamentPlayoffBracketLiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TournamentPlayoffBracketLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_tournament_broadcasts_all_playoff_games(): void
    {
        Event::fake([TournamentPlayoffBracketUpdated::class]);

        [$tournament, $game] = $this->seedPlayoffGame();

        app(TournamentPlayoffBracketLiveService::class)->pushTournament($tournament->id);

        Event::assertDispatched(TournamentPlayoffBracketUpdated::class, function (TournamentPlayoffBracketUpdated $event) use ($tournament, $game) {
            return $event->tournamentId === $tournament->id
                && $event->payload['tournamentId'] === $tournament->id
                && count($event->payload['games']) === 1
                && $event->payload['games'][0]['id'] === $game->id
                && $event->payload['games'][0]['player1Name'] === 'Host'
                && $event->payload['games'][0]['status'] === GameStatus::IN_PROGRESS->value;
        });
    }

    public function test_snapshot_and_endpoint_include_playoff_games(): void
    {
        [$tournament, $game] = $this->seedPlayoffGame();

        $snapshot = app(TournamentPlayoffBracketLiveService::class)->snapshot($tournament->id);

        $this->assertSame($tournament->id, $snapshot['tournamentId']);
        $this->assertCount(1, $snapshot['games']);
        $this->assertSame($game->id, $snapshot['games'][0]['id']);
        $this->assertSame(1, $snapshot['games'][0]['player1Score']);

        $this->getJson(route('tournaments.playoff-live', $tournament))
            ->assertOk()
            ->assertJsonPath('tournamentId', $tournament->id)
            ->assertJsonPath('games.0.id', $game->id);
    }

    public function test_playoff_tab_renders_third_place_outside_main_tree(): void
    {
        [$tournament] = $this->seedPlayoffGame();

        PlayoffGame::create([
            'tournament_id' => $tournament->id,
            'round' => 'THIRD',
            'slot' => 'THIRD',
            'status' => GameStatus::SCHEDULED,
        ]);
        PlayoffGame::create([
            'tournament_id' => $tournament->id,
            'round' => 'FINAL',
            'slot' => 'FINAL',
            'status' => GameStatus::SCHEDULED,
        ]);

        $html = $this->get(route('tournaments.show', $tournament).'?tab=playoff')
            ->assertOk()
            ->getContent();

        $this->assertNotFalse(strpos($html, 'Mecz o 3. miejsce'));
        $this->assertNotFalse(strpos($html, 'Finał'));
        $this->assertNotFalse(strpos($html, 'bracket-third'));

        $thirdBlock = strpos($html, 'bracket-third');
        $finalLabel = strpos($html, 'Finał');
        $this->assertNotFalse($thirdBlock);
        $this->assertNotFalse($finalLabel);
        $this->assertGreaterThan($finalLabel, $thirdBlock);
    }

    /**
     * @return array{0: Tournament, 1: PlayoffGame}
     */
    private function seedPlayoffGame(): array
    {
        $user = User::factory()->create(['email' => 'playoff-live@test.com']);
        app(PlayerService::class)->create('Host', $user->id);

        $organization = Organization::create(['name' => 'L', 'description' => '']);
        $season = Season::create([
            'name' => 'S',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $tournament = Tournament::create([
            'name' => 'Live Playoff',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
        ]);

        $p1 = Player::where('user_id', $user->id)->first();
        $p2 = Player::create([
            'name' => 'P2',
            'season_id' => $season->id,
            'organization_id' => $organization->id,
        ]);

        $game = PlayoffGame::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'round' => 'QUARTER',
            'slot' => 'QF_1',
            'status' => GameStatus::IN_PROGRESS,
            'player1_score' => 1,
            'player2_score' => 0,
        ]);

        return [$tournament, $game];
    }
}
