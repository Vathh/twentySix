<?php

/**
 * One-off: finish group games for a tournament, leaving one scheduled game per group.
 * Usage: php scripts/seed_tournament_group_results.php 13
 */

use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Models\Game\Game;
use App\Models\Tournament\Tournament;
use App\Services\Game\GameService;
use App\Services\GameScoring\GameResultCorrectionService;
use App\Support\GameScoring\MatchFormat;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tournamentId = (int) ($argv[1] ?? 0);
if ($tournamentId <= 0) {
    fwrite(STDERR, "Usage: php scripts/seed_tournament_group_results.php <tournament_id>\n");
    exit(1);
}

$tournament = Tournament::find($tournamentId);
if ($tournament === null) {
    fwrite(STDERR, "Tournament {$tournamentId} not found.\n");
    exit(1);
}

/** @var GameService $gameService */
$gameService = app(GameService::class);
/** @var GameResultCorrectionService $correctionService */
$correctionService = app(GameResultCorrectionService::class);

$groupNumbers = Game::query()
    ->where('tournament_id', $tournamentId)
    ->distinct()
    ->orderBy('group_number')
    ->pluck('group_number');

$finished = 0;
$skipped = 0;

foreach ($groupNumbers as $groupNumber) {
    $games = Game::query()
        ->where('tournament_id', $tournamentId)
        ->where('group_number', $groupNumber)
        ->orderBy('id')
        ->get();

    $toFinish = $games->filter(
        fn (Game $g) => $g->status === GameStatus::SCHEDULED || $g->status === GameStatus::IN_PROGRESS,
    );

    if ($toFinish->isEmpty()) {
        echo "Group {$groupNumber}: no open games, skip.\n";
        continue;
    }

    $leave = $toFinish->first();
    echo "Group {$groupNumber}: leave game #{$leave->id} scheduled ({$leave->player1_id} vs {$leave->player2_id})\n";

    foreach ($toFinish as $game) {
        if ($game->id === $leave->id) {
            $skipped++;
            continue;
        }

        $format = MatchFormat::fromRecord($game);
        $toWin = $format->scoreToWin();
        $p1Wins = random_int(0, 1) === 1;
        $p1Score = $p1Wins ? $toWin : random_int(0, max(0, $toWin - 1));
        $p2Score = $p1Wins ? random_int(0, max(0, $toWin - 1)) : $toWin;
        if ($p1Score === $p2Score) {
            $p2Score = $p1Wins ? max(0, $toWin - 1) : $toWin;
        }

        $winnerId = $p1Wins ? (int) $game->player1_id : (int) $game->player2_id;

        if ($game->status === GameStatus::FINISHED) {
            continue;
        }

        if ($game->status === GameStatus::IN_PROGRESS) {
            $correctionService->applyFromWeb(
                \App\Enums\GameKind::GROUP,
                (int) $game->id,
                $p1Score,
                $p2Score,
            );
        } else {
            $dto = new UpdateGameDTO(
                new GameResultDTO(
                    gameId: (int) $game->id,
                    type: GameType::GROUP,
                    player1Id: (int) $game->player1_id,
                    player2Id: (int) $game->player2_id,
                    player1Score: $p1Score,
                    player2Score: $p2Score,
                    winnerId: $winnerId,
                    tournamentId: $tournamentId,
                    groupNumber: (int) $groupNumber,
                ),
                achievementsDTOs: [],
            );

            if (! $gameService->update($dto)) {
                fwrite(STDERR, "Failed to finish game #{$game->id}\n");
                exit(1);
            }
        }

        $finished++;
        echo "  finished #{$game->id}: {$p1Score}:{$p2Score}\n";
    }
}

echo "Done. Finished {$finished} games, left {$skipped} scheduled (one per group).\n";
