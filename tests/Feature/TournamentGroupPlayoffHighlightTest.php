<?php

namespace Tests\Feature;

use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\GameType;
use App\Models\Game\Game;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PointScheme\PointScheme;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Game\GameService;
use App\Services\Player\PlayerService;
use App\ViewModels\TournamentDataViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InsertsPointSchemeRules;
use Tests\Support\SeedsTournamentParticipants;
use Tests\TestCase;

class TournamentGroupPlayoffHighlightTest extends TestCase
{
    use InsertsPointSchemeRules;
    use RefreshDatabase;
    use SeedsTournamentParticipants;

    public function test_playoff_badges_hidden_until_a_player_has_played_and_occupies_advance_place(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-highlight@test.com',
            'can_create_organizations' => true,
        ]);
        $organization = Organization::create(['name' => 'Org', 'description' => 'T']);
        $organization->admins()->attach($admin->id);
        $season = Season::create([
            'name' => 'Sezon',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $season->admins()->attach($admin->id);

        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $admin->id);
        $host = Player::where('user_id', $admin->id)->firstOrFail();
        $host->update(['season_id' => $season->id, 'organization_id' => $organization->id]);

        $players = collect([$host]);
        for ($i = 2; $i <= 6; $i++) {
            $players->push(Player::create([
                'name' => 'P'.$i,
                'season_id' => $season->id,
                'organization_id' => $organization->id,
            ]));
        }

        $scheme = PointScheme::create([
            'name' => 'od 2 do 8 osob',
            'min_players' => 2,
            'max_players' => 8,
        ]);
        $this->insertDefaultSeRules($scheme->id);

        $this->actingAs($admin);
        $tournament = Tournament::create([
            'name' => 'Grupy highlight',
            'season_id' => $season->id,
            'date' => '2024-06-01',
        ]);
        $this->addPlayersToTournamentPool($tournament, $players->all(), $admin);

        $this->post("/tournaments/{$tournament->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => 2,
            'playoffBracketSize' => 4,
        ])->assertRedirect("/tournaments/{$tournament->id}");

        $html = $this->get("/tournaments/{$tournament->id}")->assertOk()->getContent();
        $this->assertGreaterThan(
            0,
            preg_match_all('/data-playoff-badge/', $html),
        );
        $this->assertSame(
            preg_match_all('/data-playoff-badge/', $html),
            preg_match_all('/data-playoff-badge[^>]*\bhidden\b/', $html),
        );

        $loaded = $this->tournamentWithRelations($tournament->id);
        $highlights = (new TournamentDataViewModel($loaded))->groupPlayoffHighlights();
        foreach ($highlights as $highlight) {
            $this->assertFalse($highlight['complete']);
            $this->assertSame([], $highlight['advancingPlayerIds']);
        }

        $game = Game::where('tournament_id', $tournament->id)->orderBy('id')->firstOrFail();
        $this->assertTrue(app(GameService::class)->update(new UpdateGameDTO(
            gameResultDTO: new GameResultDTO(
                gameId: $game->id,
                type: GameType::GROUP,
                player1Id: (int) $game->player1_id,
                player2Id: (int) $game->player2_id,
                player1Score: 2,
                player2Score: 0,
                winnerId: (int) $game->player1_id,
                tournamentId: $tournament->id,
                groupNumber: (int) $game->group_number,
            ),
            achievementsDTOs: [],
        )));

        $loaded = $this->tournamentWithRelations($tournament->id);
        $highlights = (new TournamentDataViewModel($loaded))->groupPlayoffHighlights();
        $groupHighlight = $highlights[(int) $game->group_number];
        $this->assertNotEmpty($groupHighlight['advancingPlayerIds']);
        $this->assertContains((int) $game->player1_id, $groupHighlight['advancingPlayerIds']);

        foreach ($highlights as $groupNumber => $highlight) {
            foreach ($highlight['advancingPlayerIds'] as $playerId) {
                $standing = $loaded->groupStandings
                    ->first(fn ($row) => (int) $row->player_id === (int) $playerId
                        && (int) $row->group_number === (int) $groupNumber);
                $this->assertNotNull($standing);
                $this->assertGreaterThan(0, (int) $standing->games_played);
            }
        }

        $htmlAfter = $this->get("/tournaments/{$tournament->id}")->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/data-playoff-badge(?![^>]*\bhidden\b)/',
            $htmlAfter,
        );
    }

    private function tournamentWithRelations(int $tournamentId): Tournament
    {
        return Tournament::query()
            ->with(['games.player1', 'games.player2', 'games.winner', 'groupStandings.player'])
            ->findOrFail($tournamentId);
    }
}
