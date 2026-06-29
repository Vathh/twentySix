<?php

namespace App\Http\Controllers;

use App\Enums\GameKind;
use App\Enums\GameStatus;
use App\Http\Requests\WebGameResultRequest;
use App\Services\GameScoring\GameAuthorizationService;
use App\Services\GameScoring\GameDetailService;
use App\Services\GameScoring\GameResultCorrectionService;
use App\Services\GameScoring\GameScoringService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GameViewController extends Controller
{
    public function __construct(
        private GameDetailService $gameDetailService,
        private GameScoringService $gameScoringService,
        private GameResultCorrectionService $gameResultCorrectionService,
        private GameAuthorizationService $gameAuthorizationService,
    ) {
    }

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

        try {
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
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('games.show', ['type' => $type, 'id' => $id])
            ->with('success', 'Wynik meczu został zapisany.');
    }

    public function live(string $type, int $id): View|RedirectResponse
    {
        $kind = GameDetailService::kindFromRoute($type);
        $detail = $this->gameDetailService->build($kind, $id);

        if ($detail['status'] === GameStatus::FINISHED->value) {
            return redirect()->route('games.show', ['type' => $type, 'id' => $id]);
        }

        [$context, $game] = $this->resolveScoringGame($kind, $id);
        $initialState = $this->gameScoringService->getState($context, $game);

        return view('games.live', array_merge($detail, [
            'initialState' => $initialState,
            'liveStateUrl' => route('games.live.state', ['type' => $type, 'id' => $id]),
            'reverb' => $this->reverbClientConfig(),
        ]));
    }

    public function liveState(string $type, int $id): JsonResponse
    {
        $kind = GameDetailService::kindFromRoute($type);
        $detail = $this->gameDetailService->build($kind, $id);

        if ($detail['status'] === GameStatus::FINISHED->value) {
            return response()->json(['message' => 'Mecz zakończony.'], 410);
        }

        [$context, $game] = $this->resolveScoringGame($kind, $id);

        return response()->json($this->gameScoringService->getState($context, $game));
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
        };
    }

    /**
     * @return array{key: string, host: string, port: int, scheme: string}
     */
    private function reverbClientConfig(): array
    {
        return [
            'key' => (string) config('broadcasting.connections.reverb.key'),
            'host' => (string) env('REVERB_HOST', '127.0.0.1'),
            'port' => (int) env('REVERB_PORT', 8080),
            'scheme' => (string) env('REVERB_SCHEME', 'http'),
        ];
    }
}
