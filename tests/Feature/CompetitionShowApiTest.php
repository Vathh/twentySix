<?php

namespace Tests\Feature;

use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionShowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_organization_show(): void
    {
        $organization = Organization::create(['name' => 'L', 'description' => '']);

        $this->getJson('/api/organizations/'.$organization->id)->assertUnauthorized();
    }

    public function test_organization_show_returns_meta_and_seasons_without_standings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $organization = Organization::create(['name' => 'Organizacja A', 'description' => 'Opis organizacji']);
        Season::create([
            'name' => 'Sezon 1',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $this->getJson('/api/organizations/'.$organization->id)
            ->assertOk()
            ->assertJsonPath('organization.name', 'Organizacja A')
            ->assertJsonPath('organization.description', 'Opis organizacji')
            ->assertJsonStructure([
                'organization' => ['id', 'name', 'description', 'createdAt', 'updatedAt'],
                'seasons' => [['id', 'name']],
            ])
            ->assertJsonMissingPath('standings')
            ->assertJsonCount(1, 'seasons')
            ->assertJsonPath('seasons.0.name', 'Sezon 1');
    }

    public function test_organization_show_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/organizations/999999')->assertNotFound();
    }

    public function test_season_show_returns_organization_tournaments_and_standings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $organization = Organization::create(['name' => 'Organizacja B', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon X',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
        ]);
        $tournament = Tournament::create([
            'name' => 'Turniej A',
            'season_id' => $season->id,
            'date' => '2024-03-10',
        ]);
        $playerA = Player::create(['name' => 'Anna', 'user_id' => null]);
        $playerB = Player::create(['name' => 'Bartek', 'user_id' => null]);
        TournamentResult::create([
            'season_id' => $season->id,
            'tournament_id' => $tournament->id,
            'player_id' => $playerA->id,
            'points' => 10,
            'place' => 1,
        ]);
        TournamentResult::create([
            'season_id' => $season->id,
            'tournament_id' => $tournament->id,
            'player_id' => $playerB->id,
            'points' => 5,
            'place' => 2,
        ]);

        $this->getJson('/api/seasons/'.$season->id)
            ->assertOk()
            ->assertJsonPath('season.name', 'Sezon X')
            ->assertJsonPath('organization.name', 'Organizacja B')
            ->assertJsonStructure([
                'season' => ['id', 'name', 'startDate', 'endDate', 'updatedAt'],
                'organization' => ['id', 'name'],
                'tournaments' => [['id', 'name', 'date', 'statusLabel', 'statusVariant']],
                'standings' => [[
                    'place',
                    'playerId',
                    'playerName',
                    'userId',
                    'points',
                    'countMax',
                    'count170Plus',
                    'countQf',
                    'countHf',
                    'bestQf',
                    'bestHf',
                ]],
            ])
            ->assertJsonPath('standingsHasMore', false)
            ->assertJsonCount(1, 'tournaments')
            ->assertJsonPath('tournaments.0.name', 'Turniej A')
            ->assertJsonCount(2, 'standings')
            ->assertJsonPath('standings.0.playerName', 'Anna')
            ->assertJsonPath('standings.0.points', 10)
            ->assertJsonPath('standings.1.playerName', 'Bartek')
            ->assertJsonPath('standings.1.points', 5);
    }

    public function test_season_standings_paginate(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $organization = Organization::create(['name' => 'Organizacja P', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon P',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $tournament = Tournament::create([
            'name' => 'Turniej P',
            'season_id' => $season->id,
            'date' => '2024-04-01',
        ]);

        $perPage = \App\Services\Season\SeasonStatsService::STANDINGS_PER_PAGE;
        for ($i = 1; $i <= $perPage + 3; $i++) {
            $player = Player::create(['name' => "Gracz {$i}", 'user_id' => null]);
            TournamentResult::create([
                'season_id' => $season->id,
                'tournament_id' => $tournament->id,
                'player_id' => $player->id,
                'points' => 1000 - $i,
                'place' => $i,
            ]);
        }

        $this->getJson('/api/seasons/'.$season->id)
            ->assertOk()
            ->assertJsonPath('standingsHasMore', true)
            ->assertJsonCount($perPage, 'standings');

        $this->getJson('/api/seasons/'.$season->id.'/standings?page=2')
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(3, 'items')
            ->assertJsonStructure([
                'items' => [['place', 'playerId', 'playerName', 'points']],
                'has_more',
            ]);
    }

    public function test_tournament_show_returns_tabs_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $organization = Organization::create(['name' => 'Organizacja C', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon Y',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $tournament = Tournament::create([
            'name' => 'Turniej B',
            'season_id' => $season->id,
            'date' => '2024-05-01',
        ]);

        $this->getJson('/api/tournaments/'.$tournament->id)
            ->assertOk()
            ->assertJsonPath('tournament.name', 'Turniej B')
            ->assertJsonPath('tournament.isStarted', false)
            ->assertJsonPath('organization.name', 'Organizacja C')
            ->assertJsonPath('season.name', 'Sezon Y')
            ->assertJsonPath('availableTabs', [])
            ->assertJsonStructure([
                'tournament' => [
                    'id',
                    'name',
                    'date',
                    'status',
                    'statusLabel',
                    'statusVariant',
                    'isStarted',
                    'hasPlayoff',
                    'tracksSeasonPoints',
                    'format',
                    'showStageInResults',
                ],
                'organization',
                'season',
                'availableTabs',
                'results',
                'groups',
                'playoff',
                'achievements',
            ]);
    }

    public function test_tournament_show_de_hides_stage_in_results(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $tournament = Tournament::create([
            'name' => 'DE Cup',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => \App\Enums\TournamentStatus::PLAYOFF,
            'format' => \App\Enums\TournamentFormat::DoubleElimination,
            'playoff_bracket_size' => 8,
            'tablets_count' => 1,
        ]);

        $player = Player::create(['name' => 'Eliminated', 'user_id' => null]);
        TournamentResult::create([
            'season_id' => null,
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'points' => null,
            'place' => 11,
            'elimination_stage' => \App\Enums\GameStage::SEMI->value,
        ]);

        $this->getJson('/api/tournaments/'.$tournament->id)
            ->assertOk()
            ->assertJsonPath('tournament.format', 'double_elimination')
            ->assertJsonPath('tournament.showStageInResults', false)
            ->assertJsonPath('results.0.place', 11)
            ->assertJsonPath('results.0.playerName', 'Eliminated')
            ->assertJsonPath('results.0.stageLabel', null);
    }

    public function test_tournament_show_se_keeps_stage_in_results(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $tournament = Tournament::create([
            'name' => 'SE Cup',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => \App\Enums\TournamentStatus::PLAYOFF,
            'format' => \App\Enums\TournamentFormat::SingleElimination,
            'playoff_bracket_size' => 8,
            'tablets_count' => 1,
        ]);

        $player = Player::create(['name' => 'Quarter out', 'user_id' => null]);
        TournamentResult::create([
            'season_id' => null,
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'points' => null,
            'place' => 5,
            'elimination_stage' => \App\Enums\GameStage::QUARTER->value,
        ]);

        $this->getJson('/api/tournaments/'.$tournament->id)
            ->assertOk()
            ->assertJsonPath('tournament.format', 'single_elimination')
            ->assertJsonPath('tournament.showStageInResults', true)
            ->assertJsonPath('results.0.stageLabel', 'Ćwierćfinał');
    }

    public function test_tournament_show_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/tournaments/999999')->assertNotFound();
    }
}
