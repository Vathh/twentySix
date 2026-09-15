<?php

namespace Tests\Feature;

use App\Enums\GameStage;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\GroupStanding\GroupStanding;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PointScheme\PointScheme;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use App\Services\Tournament\TournamentResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonPointsFromOverallPlaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_dropout_gets_overall_place_points_not_group_place_points(): void
    {
        $scheme = PointScheme::query()
            ->where('min_players', 4)
            ->where('max_players', 8)
            ->firstOrFail();

        [$tournament, $players] = $this->groupsPlayoffTournament($scheme->id);

        foreach ($players as $index => $player) {
            $group = $index < 4 ? 1 : 2;
            $placeInGroup = ($index % 4) + 1;

            GroupStanding::create([
                'tournament_id' => $tournament->id,
                'group_number' => $group,
                'player_id' => $player->id,
                'place' => $placeInGroup,
            ]);
        }

        app(TournamentResultService::class)->createForGroupLosers($tournament->id);

        $thirdInGroup = $players[2];
        $result = TournamentResult::query()
            ->where('tournament_id', $tournament->id)
            ->where('player_id', $thirdInGroup->id)
            ->firstOrFail();

        $this->assertSame(GameStage::GROUP, $result->elimination_stage);
        $this->assertSame(5, $result->place);
        $this->assertSame(5, $result->points);
        $this->assertNotSame(8, $result->points);
    }

    /**
     * @return array{0: Tournament, 1: list<Player>}
     */
    private function groupsPlayoffTournament(int $schemeId): array
    {
        $user = User::factory()->create(['can_create_organizations' => true]);
        $organization = Organization::create(['name' => 'Org pkt', 'description' => '']);
        $organization->admins()->attach($user->id);

        $season = Season::create([
            'name' => 'Sezon pkt',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $tournament = Tournament::create([
            'name' => 'Grupy 8',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'format' => TournamentFormat::GroupsPlayoff,
            'groups_count' => 2,
            'playoff_bracket_size' => 4,
            'group_advances' => [2, 2],
            'point_scheme_id' => $schemeId,
        ]);

        $players = [];
        for ($i = 1; $i <= 8; $i++) {
            $players[] = Player::create([
                'name' => "G{$i}",
                'season_id' => $season->id,
                'organization_id' => $organization->id,
            ]);
        }

        return [$tournament, $players];
    }
}
