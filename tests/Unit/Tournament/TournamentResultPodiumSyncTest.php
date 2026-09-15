<?php

namespace Tests\Unit\Tournament;

use App\Enums\GameStage;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PointScheme\PointScheme;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use App\Services\Tournament\TournamentResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InsertsPointSchemeRules;
use Tests\TestCase;

class TournamentResultPodiumSyncTest extends TestCase
{
    use InsertsPointSchemeRules;
    use RefreshDatabase;

    private Tournament $tournament;

    private Player $player1;

    private Player $player2;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['can_create_organizations' => true]);
        $organization = Organization::create(['name' => 'Organizacja test', 'description' => '']);
        $organization->admins()->attach($user->id);

        $season = Season::create([
            'name' => 'Sezon test',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $scheme = PointScheme::create(['name' => 'test', 'min_players' => 2, 'max_players' => 8]);
        $this->insertDefaultSeRules($scheme->id);

        $this->tournament = Tournament::create([
            'name' => 'Turniej test',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'point_scheme_id' => $scheme->id,
        ]);

        $this->player1 = Player::create(['name' => 'P1', 'season_id' => $season->id, 'organization_id' => $organization->id]);
        $this->player2 = Player::create(['name' => 'P2', 'season_id' => $season->id, 'organization_id' => $organization->id]);
    }

    public function test_sync_final_podium_swaps_places_on_correction(): void
    {
        $service = app(TournamentResultService::class);

        $service->syncFinalPodium(
            $this->tournament->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );

        $service->syncFinalPodium(
            $this->tournament->id,
            $this->player2->id,
            $this->player1->id,
            $this->player2->id,
        );

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $this->tournament->id,
            'player_id' => $this->player2->id,
            'place' => 1,
            'points' => 12,
            'elimination_stage' => GameStage::FINAL->value,
        ]);

        $this->assertDatabaseHas('tournament_results', [
            'tournament_id' => $this->tournament->id,
            'player_id' => $this->player1->id,
            'place' => 2,
            'points' => 10,
            'elimination_stage' => GameStage::FINAL->value,
        ]);

        $this->assertSame(2, TournamentResult::where('tournament_id', $this->tournament->id)->count());
    }

    public function test_clear_podium_stage_removes_final_places(): void
    {
        $service = app(TournamentResultService::class);

        $service->syncFinalPodium(
            $this->tournament->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );

        $service->clearPodiumStage($this->tournament->id, GameStage::FINAL);

        $this->assertSame(0, TournamentResult::where('tournament_id', $this->tournament->id)->count());
    }
}
