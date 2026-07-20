<?php

namespace Tests\Feature;

use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentFinished;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\LoginCode;
use App\Models\Tournament\Tournament;
use App\Services\Tournament\TournamentFinishService;
use App\Support\GameScoring\MatchFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TournamentFinishServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_try_finish_marks_tournament_revokes_codes_and_broadcasts(): void
    {
        Event::fake([TournamentFinished::class]);

        $tournament = Tournament::create([
            'name' => 'Finish me',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'groups_count' => 2,
            'playoff_bracket_size' => 2,
            'group_advances' => [1, 1],
            'tablets_count' => 2,
        ]);

        $format = MatchFormat::default()->toDatabaseColumns();

        PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => GameStage::FINAL,
            'slot' => 'FINAL',
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
        ], $format));

        PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => GameStage::THIRD,
            'slot' => 'THIRD',
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 1,
        ], $format));

        $code = LoginCode::create([
            'code' => 'TAB123',
            'tournament_id' => $tournament->id,
            'expires_at' => now()->addYear(),
        ]);
        $token = $code->createToken('counter')->plainTextToken;
        $this->assertNotEmpty($token);

        $finished = app(TournamentFinishService::class)->tryFinish($tournament->id);

        $this->assertTrue($finished);
        $tournament->refresh();
        $this->assertSame(TournamentStatus::FINISHED, $tournament->status);
        $this->assertSame(0, LoginCode::where('tournament_id', $tournament->id)->count());
        $this->assertSame(0, PersonalAccessToken::query()->count());

        Event::assertDispatched(TournamentFinished::class, function (TournamentFinished $event) use ($tournament) {
            return $event->tournamentId === $tournament->id;
        });
    }

    public function test_try_finish_waits_until_all_playoff_games_are_done(): void
    {
        Event::fake([TournamentFinished::class]);

        $tournament = Tournament::create([
            'name' => 'Almost done',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'groups_count' => 2,
            'playoff_bracket_size' => 2,
            'group_advances' => [1, 1],
        ]);

        $format = MatchFormat::default()->toDatabaseColumns();

        PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => GameStage::FINAL,
            'slot' => 'FINAL',
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
        ], $format));

        PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => GameStage::THIRD,
            'slot' => 'THIRD',
            'status' => GameStatus::SCHEDULED,
            'player1_score' => 0,
            'player2_score' => 0,
        ], $format));

        LoginCode::create([
            'code' => 'KEEP99',
            'tournament_id' => $tournament->id,
            'expires_at' => now()->addYear(),
        ]);

        $finished = app(TournamentFinishService::class)->tryFinish($tournament->id);

        $this->assertFalse($finished);
        $tournament->refresh();
        $this->assertSame(TournamentStatus::PLAYOFF, $tournament->status);
        $this->assertSame(1, LoginCode::where('tournament_id', $tournament->id)->count());
        Event::assertNotDispatched(TournamentFinished::class);
    }

    public function test_tournament_login_rejected_when_finished(): void
    {
        $tournament = Tournament::create([
            'name' => 'Done',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::FINISHED,
        ]);

        LoginCode::create([
            'code' => 'OLD777',
            'tournament_id' => $tournament->id,
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->postJson('/api/login', ['code' => 'OLD777']);

        $response->assertUnauthorized();
        $response->assertJsonFragment([
            'message' => 'Turniej zakończony — kody sędziowania są już nieważne.',
        ]);
    }
}
