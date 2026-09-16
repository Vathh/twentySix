<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\GrandFinalMode;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Factories\DoubleEliminationBracketFactory;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\Tournament;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\Tournament\TournamentMatchFormatRepository;
use App\Services\Game\GameService;
use App\Services\PlayoffGame\PlayoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoubleEliminationGrandFinalPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_formats_from_form_apply_to_de_rounds(): void
    {
        $tournament = $this->deTournament();
        $players = $this->fourPlayers();

        app(TournamentMatchFormatRepository::class)->saveForTournament($tournament->id, [
            'SEMI' => ['startingScore' => 101, 'legsToWinSet' => 1, 'setsToWinMatch' => 1],
            'FINAL' => ['startingScore' => 301, 'legsToWinSet' => 1, 'setsToWinMatch' => 1],
        ]);

        app(PlayoffService::class)->generateDoubleEliminationBracket(
            $tournament->id,
            $players->pluck('id')->all(),
        );

        $w0 = PlayoffGame::query()->where('tournament_id', $tournament->id)->where('round', 'W0')->firstOrFail();
        $gf = PlayoffGame::query()->where('tournament_id', $tournament->id)->where('slot', 'GF1')->firstOrFail();

        $this->assertSame(101, (int) $w0->starting_score);
        $this->assertSame(1, (int) $w0->legs_to_win_set);
        $this->assertSame(301, (int) $gf->starting_score);
        $this->assertSame(1, (int) $gf->legs_to_win_set);
        $this->assertSame(
            0,
            PlayoffGame::query()
                ->where('tournament_id', $tournament->id)
                ->where('starting_score', MatchFormat::DEFAULT_STARTING_SCORE)
                ->where('legs_to_win_set', MatchFormat::DEFAULT_LEGS_TO_WIN_SET)
                ->count(),
        );
    }

    public function test_lb_final_winner_plays_wb_champion_in_gf1_and_lb_win_opens_gf2(): void
    {
        $tournament = $this->deTournament();
        $anna = Player::create(['name' => 'Anna Nowak']);
        $hahaha = Player::create(['name' => 'Hahaha']);
        $b = Player::create(['name' => 'B']);
        $d = Player::create(['name' => 'D']);

        $domains = app(DoubleEliminationBracketFactory::class)->create(
            $tournament->id,
            4,
            [[$anna->id, $b->id], [$hahaha->id, $d->id]],
            true,
        );
        app(PlayoffGameRepository::class)->createMany($domains);
        app(PlayoffService::class)->resolveScheduledByes($tournament->id);

        $w01 = $this->slot($tournament->id, 'W0-1');
        $w02 = $this->slot($tournament->id, 'W0-2');
        $this->finish($w01, $anna->id);
        $this->finish($w02, $hahaha->id);

        $wbFinal = $this->slot($tournament->id, 'W1-1');
        $this->assertEqualsCanonicalizing(
            [$anna->id, $hahaha->id],
            [(int) $wbFinal->player1_id, (int) $wbFinal->player2_id],
        );
        $this->finish($wbFinal, $anna->id);

        $gf1 = $this->slot($tournament->id, 'GF1');
        $this->assertSame($anna->id, (int) $gf1->player1_id);
        $this->assertNull($gf1->player2_id);

        $l0 = $this->slot($tournament->id, 'L0-1');
        $this->finish($l0, $b->id);

        $l1 = $this->slot($tournament->id, 'L1-1');
        $this->assertEqualsCanonicalizing(
            [$b->id, $hahaha->id],
            [(int) $l1->player1_id, (int) $l1->player2_id],
        );
        $this->finish($l1, $hahaha->id);

        $gf1->refresh();
        $this->assertSame($anna->id, (int) $gf1->player1_id);
        $this->assertSame($hahaha->id, (int) $gf1->player2_id);
        $this->assertSame(GameStatus::SCHEDULED, $gf1->status);

        $this->finish($gf1, $hahaha->id);

        $gf2 = $this->slot($tournament->id, 'GF2');
        $this->assertEqualsCanonicalizing(
            [$anna->id, $hahaha->id],
            [(int) $gf2->player1_id, (int) $gf2->player2_id],
        );
        $this->assertSame(GameStatus::SCHEDULED, $gf2->status);
        $this->assertSame(TournamentStatus::PLAYOFF, $tournament->fresh()->status);

        $this->finish($gf2, $hahaha->id);
        $this->assertSame(TournamentStatus::FINISHED, $tournament->fresh()->status);
    }

    private function deTournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Turniej DE',
            'season_id' => null,
            'date' => '2026-09-01',
            'status' => TournamentStatus::PLAYOFF,
            'format' => TournamentFormat::DoubleElimination,
            'grand_final_mode' => GrandFinalMode::Reset,
            'playoff_bracket_size' => 4,
            'tablets_count' => 1,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Player>
     */
    private function fourPlayers()
    {
        return collect([
            Player::create(['name' => 'A']),
            Player::create(['name' => 'B']),
            Player::create(['name' => 'C']),
            Player::create(['name' => 'D']),
        ]);
    }

    private function slot(int $tournamentId, string $slot): PlayoffGame
    {
        return PlayoffGame::query()
            ->where('tournament_id', $tournamentId)
            ->where('slot', $slot)
            ->firstOrFail();
    }

    private function finish(PlayoffGame $game, int $winnerId): void
    {
        $p1 = (int) $game->player1_id;
        $p2 = (int) $game->player2_id;
        $ok = app(GameService::class)->update(new UpdateGameDTO(
            gameResultDTO: new GameResultDTO(
                gameId: (int) $game->id,
                type: GameType::PLAYOFF,
                player1Id: $p1,
                player2Id: $p2,
                player1Score: $winnerId === $p1 ? 1 : 0,
                player2Score: $winnerId === $p2 ? 1 : 0,
                winnerId: $winnerId,
                tournamentId: (int) $game->tournament_id,
            ),
            achievementsDTOs: [],
        ));

        $this->assertTrue($ok, 'Nie udało się zamknąć '.$game->slot);
        $game->refresh();
        $this->assertSame(GameStatus::FINISHED, $game->status);
        $this->assertSame($winnerId, (int) $game->winner_id);
    }
}
