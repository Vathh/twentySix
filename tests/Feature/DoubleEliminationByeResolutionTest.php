<?php

namespace Tests\Feature;

use App\Domain\Game\PlayoffGameDomain;
use App\Domain\GameScoring\MatchFormat;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Services\PlayoffGame\PlayoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoubleEliminationByeResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_facing_bye_mid_bracket_auto_advances(): void
    {
        $tournament = Tournament::create([
            'name' => 'DE Bye cascade',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'format' => TournamentFormat::DoubleElimination,
            'playoff_bracket_size' => 8,
            'tablets_count' => 1,
        ]);

        $byeId = app(PlayerRepository::class)->byePlayerId();
        $p1 = Player::create(['name' => 'Dropper']);
        $format = MatchFormat::default()->toDatabaseColumns();

        $lbR2 = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'losers',
            'round' => 'L1',
            'slot' => 'L1-1',
            'player1_id' => $byeId,
            'player2_id' => $p1->id,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'L2-1-A',
            'loser_destination_slot' => null,
        ], $format));

        $lbR3 = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'losers',
            'round' => 'L2',
            'slot' => 'L2-1',
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'GF1-B',
            'loser_destination_slot' => null,
        ], $format));

        app(PlayoffService::class)->resolveScheduledByes($tournament->id);

        $lbR2->refresh();
        $lbR3->refresh();

        $this->assertSame(GameStatus::FINISHED, $lbR2->status);
        $this->assertSame($p1->id, $lbR2->winner_id);
        $this->assertSame($p1->id, $lbR3->player1_id);
        $this->assertNull($lbR3->player2_id);
        $this->assertSame(GameStatus::SCHEDULED, $lbR3->status);
    }

    public function test_bye_vs_bye_advances_bye_into_next_round_and_waits_for_feeder(): void
    {
        $tournament = Tournament::create([
            'name' => 'DE double bye',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'format' => TournamentFormat::DoubleElimination,
            'playoff_bracket_size' => 8,
            'tablets_count' => 1,
        ]);

        $byeId = app(PlayerRepository::class)->byePlayerId();
        $format = MatchFormat::default()->toDatabaseColumns();

        $w0 = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'winners',
            'round' => 'W0',
            'slot' => 'W0-1',
            'player1_id' => $byeId,
            'player2_id' => $byeId,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'W1-1-A',
            'loser_destination_slot' => 'L0-1-A',
        ], $format));

        $w1 = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'winners',
            'round' => 'W1',
            'slot' => 'W1-1',
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'W2-1-A',
            'loser_destination_slot' => null,
        ], $format));

        $l0 = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'losers',
            'round' => 'L0',
            'slot' => 'L0-1',
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'L1-1-A',
            'loser_destination_slot' => null,
        ], $format));

        app(PlayoffService::class)->resolveScheduledByes($tournament->id);

        $w0->refresh();
        $w1->refresh();
        $l0->refresh();

        $this->assertSame(GameStatus::FINISHED, $w0->status);
        $this->assertSame($byeId, (int) $w0->winner_id);
        $this->assertSame($byeId, (int) $w1->player1_id);
        $this->assertNull($w1->player2_id);
        $this->assertSame(GameStatus::SCHEDULED, $w1->status);
        $this->assertSame($byeId, (int) $l0->player1_id);
        $this->assertNull($l0->player2_id);
        $this->assertSame(GameStatus::SCHEDULED, $l0->status);
    }

    public function test_advancement_into_bye_slot_triggers_auto_resolve(): void
    {
        $tournament = Tournament::create([
            'name' => 'DE drop onto bye',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'format' => TournamentFormat::DoubleElimination,
            'playoff_bracket_size' => 8,
            'tablets_count' => 1,
        ]);

        $byeId = app(PlayerRepository::class)->byePlayerId();
        $winner = Player::create(['name' => 'Winner']);
        $loser = Player::create(['name' => 'Loser']);
        $format = MatchFormat::default()->toDatabaseColumns();

        $wb = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'winners',
            'round' => 'W1',
            'slot' => 'W1-1',
            'player1_id' => $winner->id,
            'player2_id' => $loser->id,
            'status' => GameStatus::IN_PROGRESS,
            'winner_destination_slot' => 'W2-1-A',
            'loser_destination_slot' => 'L1-1-B',
        ], $format));

        PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'winners',
            'round' => 'W2',
            'slot' => 'W2-1',
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'GF1-A',
            'loser_destination_slot' => null,
        ], $format));

        $lb = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'losers',
            'round' => 'L1',
            'slot' => 'L1-1',
            'player1_id' => $byeId,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'L2-1-A',
            'loser_destination_slot' => null,
        ], $format));

        $lbNext = PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'bracket_side' => 'losers',
            'round' => 'L2',
            'slot' => 'L2-1',
            'player1_id' => null,
            'player2_id' => null,
            'status' => GameStatus::SCHEDULED,
            'winner_destination_slot' => 'GF1-B',
            'loser_destination_slot' => null,
        ], $format));

        $dto = new GameResultDTO(
            gameId: $wb->id,
            type: GameType::PLAYOFF,
            player1Id: $winner->id,
            player2Id: $loser->id,
            player1Score: 2,
            player2Score: 0,
            winnerId: $winner->id,
            tournamentId: $tournament->id,
        );

        $domain = app(PlayoffGameRepository::class)->find($wb->id);
        $this->assertInstanceOf(PlayoffGameDomain::class, $domain);

        app(PlayoffService::class)->update($dto, $domain);

        $lb->refresh();
        $lbNext->refresh();

        $this->assertSame(GameStatus::FINISHED, $lb->status);
        $this->assertSame($loser->id, $lb->winner_id);
        $this->assertSame($loser->id, $lbNext->player1_id);
    }

    public function test_generate_de_with_byes_resolves_initial_player_byes(): void
    {
        $tournament = Tournament::create([
            'name' => 'DE six players',
            'season_id' => null,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
            'format' => TournamentFormat::DoubleElimination,
            'playoff_bracket_size' => 8,
            'tablets_count' => 1,
        ]);

        $byeId = app(PlayerRepository::class)->byePlayerId();
        $players = collect(range(1, 6))->map(fn (int $i) => Player::create(['name' => "P{$i}"]));

        app(PlayoffService::class)->generateDoubleEliminationBracket(
            $tournament->id,
            $players->pluck('id')->all(),
        );

        $w0 = PlayoffGame::query()
            ->where('tournament_id', $tournament->id)
            ->where('round', 'W0')
            ->get();

        $this->assertCount(4, $w0);

        foreach ($w0 as $game) {
            $hasBye = (int) $game->player1_id === $byeId || (int) $game->player2_id === $byeId;
            if ($hasBye) {
                $this->assertSame(GameStatus::FINISHED, $game->status);
            } else {
                $this->assertSame(GameStatus::SCHEDULED, $game->status);
            }
        }

        $this->assertSame(
            6,
            $w0->flatMap(fn ($g) => array_filter([$g->player1_id, $g->player2_id], fn ($id) => (int) $id !== $byeId))
                ->unique()
                ->count(),
        );
    }
}
