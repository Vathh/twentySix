<?php

namespace App\Http\Controllers;

use App\Enums\GameKind;
use App\Enums\GameStatus;
use App\Http\Requests\WebGameResultRequest;
use App\Services\GameScoring\GameAuthorizationService;
use App\Services\GameScoring\GameCancelService;
use App\Services\GameScoring\GameDetailService;
use App\Services\GameScoring\GameResultCorrectionService;
use App\Services\GameScoring\GameScoringService;
use App\Support\Broadcasting\ReverbClientConfig;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameViewController extends Controller
{
    public function __construct(
        private GameDetailService $gameDetailService,
        private GameScoringService $gameScoringService,
        private GameResultCorrectionService $gameResultCorrectionService,
        private GameCancelService $gameCancelService,
        private GameAuthorizationService $gameAuthorizationService,
    ) {}

    public function show(string $type, int $id): View
    {
        $detail = $this->gameDetailService->build(
            GameDetailService::kindFromRoute($type),
            $id,
        );

        return view('games.show', $detail);
    }

    public function updateResult(WebGameResultRequest $request, string $type, int $id): RedirectResponse
    {
        $kind = GameDetailService::kindFromRoute($type);
        $detail = $this->gameDetailService->build($kind, $id);

        $this->gameAuthorizationService->authorizeTournamentGame(
            $detail['tournamentId'] ?? null,
            $kind,
        );

        if ($request->boolean('walkover')) {
            $winnerId = (int) $request->validated('winner_id');
            $this->assertWinnerIsParticipant($detail, $winnerId);
            $this->gameResultCorrectionService->applyWalkoverFromWeb($kind, $id, $winnerId);
        } else {
            $validated = $request->validated();
            $this->gameResultCorrectionService->applyFromWeb(
                $kind,
                $id,
                (int) $validated['player1_score'],
                (int) $validated['player2_score'],
            );
        }

        return redirect()
            ->route('games.show', ['type' => $type, 'id' => $id])
            ->with('success', 'Wynik meczu został zapisany.');
    }

    public function cancel(Request $request, string $type, int $id): RedirectResponse
    {
        $kind = GameDetailService::kindFromRoute($type);
        $detail = $this->gameDetailService->build($kind, $id);

        $this->gameAuthorizationService->authorizeTournamentGame(
            $detail['tournamentId'] ?? null,
            $kind,
        );

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Hasło jest nieprawidłowe.',
        ]);

        $this->gameCancelService->cancel($kind, $id);

        return redirect()
            ->route('games.show', ['type' => $type, 'id' => $id])
            ->with('success', 'Mecz anulowany. Można rozegrać go od nowa.');
    }

    public function live(string $type, int $id): View|RedirectResponse
    {
        $kind = GameDetailService::kindFromRoute($type);
        [$detail, $context, $game] = $this->gameDetailService->buildShell($kind, $id);

        if ($detail['status'] === GameStatus::FINISHED->value) {
            return redirect()->route('games.show', ['type' => $type, 'id' => $id]);
        }

        $initialState = $this->gameScoringService->getState($context, $game);

        return view('games.live', array_merge($detail, [
            'initialState' => $initialState,
            'liveStateUrl' => route('games.live.state', ['type' => $type, 'id' => $id]),
            'reverb' => ReverbClientConfig::forWeb(),
        ]));
    }

    public function liveState(string $type, int $id): JsonResponse
    {
        $kind = GameDetailService::kindFromRoute($type);
        [$context, $game] = $this->resolveScoringGame($kind, $id);

        $status = $game->status instanceof \BackedEnum ? $game->status->value : (string) $game->status;
        if ($status === GameStatus::FINISHED->value) {
            return response()->json(['message' => 'Mecz zakończony.'], 410);
        }
        if ($status !== GameStatus::IN_PROGRESS->value) {
            return response()->json(['message' => 'Mecz nie jest w trakcie.'], 409);
        }

        return response()->json($this->gameScoringService->getState($context, $game));
    }

    public function overlay(Request $request, string $type, int $id): View
    {
        $kind = GameDetailService::kindFromRoute($type);
        [$detail, $context, $game] = $this->gameDetailService->buildShell($kind, $id);
        $initialState = $this->gameScoringService->getState($context, $game);

        $overlayShowUrl = $kind === GameKind::LEAGUE
            ? route('league-games.show', $id)
            : route('games.show', ['type' => $type, 'id' => $id]);

        return view('games.overlay', array_merge($detail, [
            'initialState' => $initialState,
            'liveStateUrl' => route('games.live.state', ['type' => $type, 'id' => $id]),
            'overlayShowUrl' => $overlayShowUrl,
            'overlayBg' => $request->query('bg') === 'solid' ? 'solid' : 'transparent',
            'overlayPreview' => $request->boolean('preview'),
            'reverb' => ReverbClientConfig::forWeb(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function assertWinnerIsParticipant(array $detail, int $winnerId): void
    {
        $playerIds = [(int) $detail['player1']->id, (int) $detail['player2']->id];

        if (! in_array($winnerId, $playerIds, true)) {
            throw new DomainException('Wybrany gracz nie uczestniczy w tym meczu.');
        }
    }

    /**
     * @return array{0: \App\Support\GameScoring\GameScoringContext, 1: \Illuminate\Database\Eloquent\Model}
     */
    private function resolveScoringGame(GameKind $kind, int $id): array
    {
        return match ($kind) {
            GameKind::GROUP => $this->gameScoringService->resolveGroupGame($id),
            GameKind::PLAYOFF => $this->gameScoringService->resolvePlayoffGame($id),
            GameKind::QUICK => $this->gameScoringService->resolveQuickGame($id),
            GameKind::LEAGUE => $this->gameScoringService->resolveLeagueGame($id),
        };
    }
}
