<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameStatus;
use App\Enums\PlayoffSlot;
use App\Enums\TournamentStatus;
use App\Enums\WinnerDestinationSlot;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Repositories\Player\PlayerRepository;
use App\Services\PlayoffGame\PlayoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayoffByeAdvancesThroughRoundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bye_vs_bye_in_round_of_16_advances_bye_then_player_walks_over_quarter(): void
    {
        $tournament = Tournament::create([
            'name' => 'SE bye cascade',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'playoff_bracket_size' => 8,
            'tablets_count' => 1,
        ]);

        $byeId = app(PlayerRepository::class)->byePlayerId();
        $real = Player::create(['name' => 'Alive']);
        $format = MatchFormat::default()->toDatabaseColumns();

        $eight = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'EIGHT',
            'slot' => PlayoffSlot::EIGHT_1,
            'player1_id' => $byeId,
            'player2_id' => $byeId,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => WinnerDestinationSlot::QF_1_B,
        ], $format));

        $quarter = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'QUARTER',
            'slot' => PlayoffSlot::QF_1,
            'player1_id' => $real->id,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => WinnerDestinationSlot::SEMI_1_A,
        ], $format));

        $semi = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'SEMI',
            'slot' => PlayoffSlot::SEMI_1,
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => WinnerDestinationSlot::FINAL_A,
        ], $format));

        app(PlayoffService::class)->resolveScheduledByes($tournament->id);

        $eight->refresh();
        $quarter->refresh();
        $semi->refresh();

        $this->assertSame(GameStatus::FINISHED, $eight->status);
        $this->assertSame($byeId, (int) $eight->winner_id);

        $this->assertSame($real->id, (int) $quarter->player1_id);
        $this->assertSame($byeId, (int) $quarter->player2_id);
        $this->assertSame(GameStatus::FINISHED, $quarter->status);
        $this->assertSame($real->id, (int) $quarter->winner_id);

        $this->assertSame($real->id, (int) $semi->player1_id);
        $this->assertNull($semi->player2_id);
        $this->assertSame(GameStatus::SCHEDULED, $semi->status);
    }
}
