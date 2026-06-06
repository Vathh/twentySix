<?php

namespace Tests\Feature;

use App\Enums\GameStage;
use App\Models\GroupStanding\GroupStanding;
use App\Models\League\League;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\GroupStanding\GroupStandingRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Services\PlayoffGame\PlayoffService;
use App\Support\Tournament\PlayoffFirstRoundPairing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayoffAdvanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_playoff_uses_advance_per_group_from_tournament(): void
    {
        $admin = User::factory()->create(['can_create_leagues' => true]);
        $league = League::create(['name' => 'Liga', 'description' => '']);
        $league->admins()->attach($admin->id);
        $season = Season::create([
            'name' => 'Sezon',
            'league_id' => $league->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $tournament = Tournament::create([
            'name' => 'Turniej',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'groups_count' => 2,
            'advance_per_group' => 1,
            'tablets_count' => 1,
        ]);

        $players = collect(range(1, 4))->map(fn (int $i) => Player::create([
            'name' => "Gracz {$i}",
            'season_id' => $season->id,
            'league_id' => $league->id,
        ]));

        foreach ($players as $index => $player) {
            GroupStanding::create([
                'tournament_id' => $tournament->id,
                'group_number' => ($index < 2) ? 1 : 2,
                'player_id' => $player->id,
                'place' => ($index % 2) + 1,
                'matches_played' => 1,
                'matches_won' => ($index % 2) === 0 ? 1 : 0,
                'matches_lost' => ($index % 2) === 0 ? 0 : 1,
                'legs_won' => 2,
                'legs_lost' => 0,
                'points' => ($index % 2) === 0 ? 1 : 0,
            ]);
        }

        $repo = app(GroupStandingRepository::class);
        $advance = app(TournamentRepository::class)->getAdvancePerGroup($tournament->id);

        $this->assertSame(1, $advance);
        $this->assertCount(2, $repo->getAdvancingPlayersWithGroups($tournament->id, $advance));
        $this->assertCount(2, $repo->getGroupLosers($tournament->id, $advance));

        app(PlayoffService::class)->generateBracket($tournament->id);

        $this->assertDatabaseCount('playoff_games', 1);

        $final = PlayoffGame::where('tournament_id', $tournament->id)->first();
        $this->assertSame(GameStage::FINAL->value, $final->round->value);
        $this->assertEqualsCanonicalizing(
            [$players[0]->id, $players[2]->id],
            [$final->player1_id, $final->player2_id],
        );
        $this->assertNotSame(
            GroupStanding::where('player_id', $final->player1_id)->value('group_number'),
            GroupStanding::where('player_id', $final->player2_id)->value('group_number'),
        );
    }

    public function test_playoff_first_round_respects_group_constraint_for_four_group_bracket(): void
    {
        $admin = User::factory()->create(['can_create_leagues' => true]);
        $league = League::create(['name' => 'Liga', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon',
            'league_id' => $league->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $tournament = Tournament::create([
            'name' => 'Turniej 8',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'groups_count' => 4,
            'advance_per_group' => 2,
            'tablets_count' => 2,
        ]);

        $players = collect(range(1, 8))->map(fn (int $i) => Player::create([
            'name' => "Gracz {$i}",
            'season_id' => $season->id,
            'league_id' => $league->id,
        ]));

        foreach ($players as $index => $player) {
            GroupStanding::create([
                'tournament_id' => $tournament->id,
                'group_number' => intdiv($index, 2) + 1,
                'player_id' => $player->id,
                'place' => ($index % 2) + 1,
                'matches_played' => 1,
                'matches_won' => ($index % 2) === 0 ? 1 : 0,
                'matches_lost' => ($index % 2) === 0 ? 0 : 1,
                'legs_won' => 2,
                'legs_lost' => 0,
                'points' => ($index % 2) === 0 ? 1 : 0,
            ]);
        }

        app(PlayoffService::class)->generateBracket($tournament->id);

        $groupByPlayer = GroupStanding::where('tournament_id', $tournament->id)
            ->pluck('group_number', 'player_id')
            ->all();

        $firstRoundGames = PlayoffGame::where('tournament_id', $tournament->id)
            ->where('round', GameStage::QUARTER->value)
            ->get();

        $this->assertCount(4, $firstRoundGames);

        $pairs = $firstRoundGames->map(
            fn (PlayoffGame $game) => [$game->player1_id, $game->player2_id],
        )->all();

        $this->assertTrue(PlayoffFirstRoundPairing::pairsSatisfyGroupConstraint($pairs, $groupByPlayer));
    }

    public function test_get_advance_per_group_defaults_to_two_for_legacy_tournament(): void
    {
        $tournament = Tournament::create([
            'name' => 'Legacy',
            'season_id' => null,
            'date' => '2024-06-01',
        ]);

        $this->assertSame(2, app(TournamentRepository::class)->getAdvancePerGroup($tournament->id));
    }
}
