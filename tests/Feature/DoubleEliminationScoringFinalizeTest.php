<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\BracketSide;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\PointScheme\PointScheme;
use App\Models\PointScheme\PointSchemeRule;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsTournamentTablet;
use Tests\TestCase;

class DoubleEliminationScoringFinalizeTest extends TestCase
{
    use ActsAsTournamentTablet;
    use RefreshDatabase;

    public function test_closing_lb_game_does_not_404_when_point_scheme_has_no_exact_place(): void
    {
        $user = User::factory()->create(['email' => 'de-scoring@test.com']);
        app(PlayerService::class)->create('Host', $user->id);

        $organization = Organization::create(['name' => 'Org DE', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon DE',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $scheme = PointScheme::create([
            'name' => 'od 4 do 8 osób',
            'min_players' => 4,
            'max_players' => 8,
        ]);
        PointSchemeRule::create([
            'point_scheme_id' => $scheme->id,
            'elimination_stage' => GameStage::SEMI->value,
            'place' => null,
            'points' => 8,
        ]);
        PointSchemeRule::create([
            'point_scheme_id' => $scheme->id,
            'elimination_stage' => GameStage::FINAL->value,
            'place' => 1,
            'points' => 13,
        ]);

        $tournament = Tournament::create([
            'name' => 'Turniej DE sezonu',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'format' => TournamentFormat::DoubleElimination,
            'playoff_bracket_size' => 8,
            'point_scheme_id' => $scheme->id,
            'tablets_count' => 1,
        ]);

        $winner = Player::where('user_id', $user->id)->firstOrFail();
        $loser = Player::create(['name' => 'Przegrany LB']);
        $format = (new MatchFormat(legsToWinSet: 1))->toDatabaseColumns();

        $lb = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => BracketSide::Losers,
            'round' => 'L1',
            'slot' => 'L1-1',
            'player1_id' => $winner->id,
            'player2_id' => $loser->id,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'L2-1-A',
            'loser_destination_slot' => null,
        ], $format));

        $next = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => BracketSide::Losers,
            'round' => 'L2',
            'slot' => 'L2-1',
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'GF1-B',
            'loser_destination_slot' => null,
        ], $format));

        $this->actAsTournamentTablet($tournament);

        $start = $this->postJson("/api/playoff-games/{$lb->id}/legs", [
            'player1DoubleTracked' => false,
            'player2DoubleTracked' => false,
        ]);
        $start->assertOk();
        $legId = $start->json('currentLeg.id');

        $this->postJson("/api/playoff-games/{$lb->id}/legs/{$legId}/visits", [
            'playerId' => $winner->id,
            'score' => 60,
            'remainingBefore' => 501,
            'remainingAfter' => 441,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk();

        $close = $this->postJson("/api/playoff-games/{$lb->id}/legs/{$legId}/close", [
            'winnerId' => $winner->id,
            'players' => [
                ['playerId' => $winner->id, 'doubleTracked' => false],
                ['playerId' => $loser->id, 'doubleTracked' => false],
            ],
        ]);

        $close->assertOk();

        $lb->refresh();
        $next->refresh();

        $this->assertSame(GameStatus::FINISHED, $lb->status);
        $this->assertSame($winner->id, (int) $lb->winner_id);
        $this->assertSame($winner->id, (int) $next->player1_id);

        $this->assertTrue(
            TournamentResult::query()
                ->where('tournament_id', $tournament->id)
                ->where('player_id', $loser->id)
                ->exists()
        );
    }
}
