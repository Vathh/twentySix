<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameVisit;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\Stats\TournamentAggregateRepository;
use App\Services\Player\PlayerService;
use App\ViewModels\TournamentDataViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TournamentAchievementsFromVisitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_playoff_and_group_visits_fill_achievements_without_client_post(): void
    {
        [$tournament, $player1, $player2] = $this->seedTournament();

        $group = Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
            'player1_score' => 3,
            'player2_score' => 0,
            'winner_id' => $player1->id,
        ]);
        $groupLeg = $this->legFor($group->id, null, $player1->id);
        $this->visit($groupLeg->id, $player1->id, 1, 171, closedLeg: false);
        $this->visit($groupLeg->id, $player1->id, 2, 180, bust: true);
        $this->visit($groupLeg->id, $player1->id, 3, 120, closedLeg: true);

        $playoff = PlayoffGame::create([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'main',
            'round' => 'FINAL',
            'slot' => 'FINAL',
            'player1_id' => $player2->id,
            'player2_id' => $player1->id,
            'status' => GameStatus::FINISHED,
            'player1_score' => 1,
            'player2_score' => 4,
            'winner_id' => $player1->id,
        ]);
        $playoffLeg = $this->legFor(null, $playoff->id, $player1->id);
        $this->visit($playoffLeg->id, $player1->id, 1, 180, closedLeg: false);
        $this->visit($playoffLeg->id, $player1->id, 2, 100, closedLeg: true);

        $tournament->load('achievements.player');
        $rows = (new TournamentDataViewModel($tournament))->achievements();

        $this->assertTrue($rows->has($player1->id));
        $this->assertSame(1, $rows[$player1->id]['max']);
        $this->assertSame(1, $rows[$player1->id]['one_seventy']);
        $hf = collect($rows[$player1->id]['hf'])->pluck('value')->all();
        $this->assertSame([120, 100], $hf);

        $aggregated = app(TournamentAggregateRepository::class)
            ->getAchievementsAggregatedForTournaments([$tournament->id]);
        $this->assertSame(1, $aggregated[$player1->id]['count_max']);
        $this->assertSame(1, $aggregated[$player1->id]['count_170_plus']);
        $this->assertSame(2, $aggregated[$player1->id]['count_hf']);
        $this->assertSame(120, $aggregated[$player1->id]['best_hf']);
    }

    /**
     * @return array{0: Tournament, 1: Player, 2: Player}
     */
    private function seedTournament(): array
    {
        $user = User::factory()->create();
        app(PlayerService::class)->create('Jan', $user->id);
        $player1 = Player::where('user_id', $user->id)->first();

        $organization = Organization::create(['name' => 'L', 'description' => '']);
        $season = Season::create([
            'name' => 'S',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $tournament = Tournament::create([
            'name' => 'T',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::FINISHED,
        ]);
        $player2 = Player::create([
            'name' => 'Opponent',
            'season_id' => $season->id,
            'organization_id' => $organization->id,
        ]);

        return [$tournament, $player1, $player2];
    }

    private function legFor(?int $gameId, ?int $playoffGameId, int $winnerId): GameLeg
    {
        return GameLeg::create([
            'game_id' => $gameId,
            'playoff_game_id' => $playoffGameId,
            'leg_number' => 1,
            'winner_id' => $winnerId,
            'player1_score' => 501,
            'player2_score' => 0,
            'finished_at' => now(),
        ]);
    }

    private function visit(
        int $legId,
        int $playerId,
        int $number,
        int $score,
        bool $closedLeg = false,
        bool $bust = false,
    ): void {
        GameVisit::create([
            'game_leg_id' => $legId,
            'player_id' => $playerId,
            'visit_number' => $number,
            'score' => $score,
            'remaining_before' => 501,
            'remaining_after' => $closedLeg ? 0 : 321,
            'darts_in_visit' => 3,
            'closed_leg' => $closedLeg,
            'bust' => $bust,
            'is_voided' => false,
            'client_visit_id' => (string) Str::uuid(),
        ]);
    }
}
