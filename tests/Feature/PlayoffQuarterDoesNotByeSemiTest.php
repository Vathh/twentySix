<?php

namespace Tests\Feature;

use App\Domain\Game\PlayoffGameDomain;
use App\Domain\GameScoring\MatchFormat;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\PlayoffSlot;
use App\Enums\TournamentStatus;
use App\Enums\WinnerDestinationSlot;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Services\PlayoffGame\PlayoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayoffQuarterDoesNotByeSemiTest extends TestCase
{
    use RefreshDatabase;

    public function test_finishing_one_quarter_does_not_walkover_or_void_semis(): void
    {
        $tournament = Tournament::create([
            'name' => 'SE eight players',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'playoff_bracket_size' => 8,
            'tablets_count' => 1,
        ]);

        $players = collect(range(1, 8))->map(fn (int $i) => Player::create(['name' => "P{$i}"]));
        $format = MatchFormat::default()->toDatabaseColumns();

        $quarters = [
            [PlayoffSlot::QF_1, WinnerDestinationSlot::SEMI_1_A, 0, 1],
            [PlayoffSlot::QF_2, WinnerDestinationSlot::SEMI_1_B, 2, 3],
            [PlayoffSlot::QF_3, WinnerDestinationSlot::SEMI_2_A, 4, 5],
            [PlayoffSlot::QF_4, WinnerDestinationSlot::SEMI_2_B, 6, 7],
        ];

        $firstQuarter = null;
        foreach ($quarters as [$slot, $dest, $p1, $p2]) {
            $game = PlayoffGame::create(array_merge([
                'tournament_id' => $tournament->id,
                'round' => 'QUARTER',
                'slot' => $slot,
                'player1_id' => $players[$p1]->id,
                'player2_id' => $players[$p2]->id,
                'status' => GameStatus::IN_PROGRESS,
                'winner_destination_slot' => $dest,
                'loser_destination_slot' => null,
            ], $format));
            $firstQuarter ??= $game;
        }

        $semi1 = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'SEMI',
            'slot' => PlayoffSlot::SEMI_1,
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => WinnerDestinationSlot::FINAL_A,
            'loser_destination_slot' => WinnerDestinationSlot::THIRD_A,
        ], $format));

        $semi2 = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'SEMI',
            'slot' => PlayoffSlot::SEMI_2,
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => WinnerDestinationSlot::FINAL_B,
            'loser_destination_slot' => WinnerDestinationSlot::THIRD_B,
        ], $format));

        $final = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'FINAL',
            'slot' => PlayoffSlot::FINAL,
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
        ], $format));

        $dto = new GameResultDTO(
            gameId: $firstQuarter->id,
            type: GameType::PLAYOFF,
            player1Id: $players[0]->id,
            player2Id: $players[1]->id,
            player1Score: 2,
            player2Score: 0,
            winnerId: $players[0]->id,
            tournamentId: $tournament->id,
        );

        $domain = app(PlayoffGameRepository::class)->find($firstQuarter->id);
        $this->assertInstanceOf(PlayoffGameDomain::class, $domain);

        app(PlayoffService::class)->update($dto, $domain);

        $semi1->refresh();
        $semi2->refresh();
        $final->refresh();

        $this->assertSame(GameStatus::SCHEDULED, $semi1->status);
        $this->assertSame($players[0]->id, (int) $semi1->player1_id);
        $this->assertNull($semi1->player2_id);
        $this->assertSame(0, (int) $semi1->player1_score);
        $this->assertSame(0, (int) $semi1->player2_score);

        $this->assertSame(GameStatus::SCHEDULED, $semi2->status);
        $this->assertNull($semi2->player1_id);
        $this->assertNull($semi2->player2_id);
        $this->assertSame(0, (int) $semi2->player1_score);
        $this->assertSame(0, (int) $semi2->player2_score);

        $this->assertSame(GameStatus::SCHEDULED, $final->status);
        $this->assertNull($final->player1_id);
        $this->assertNull($final->player2_id);
    }
}
