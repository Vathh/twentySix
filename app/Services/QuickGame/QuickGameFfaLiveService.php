<?php

namespace App\Services\QuickGame;

use App\Models\QuickGame\QuickGameFfaSession;
use App\Repositories\QuickGame\QuickGameFfaSessionRepository;
use App\Support\GameScoring\MatchFormat;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuickGameFfaLiveService
{
    public function __construct(
        private QuickGameFfaSessionRepository $sessionRepository,
        private QuickGameFfaScoringService $ffaScoringService,
    ) {
    }

    /**
     * Dane pod stronę live. Gdy mecz skończony: finished=true + opcjonalny showUrl.
     *
     * @return array{
     *     finished: bool,
     *     showUrl: string|null,
     *     lobbyId?: int,
     *     initialState?: array<string, mixed>,
     *     liveStateUrl?: string,
     *     broadcastChannel?: string,
     *     formatLabel?: string,
     *     reverb?: array{key: string, host: string, port: int, scheme: string}
     * }
     */
    public function buildLivePage(int $lobbyId): array
    {
        $session = $this->requireSession($lobbyId);
        $showUrl = $this->finishedShowUrl($session);

        if ($session->status === QuickGameFfaSession::STATUS_FINISHED) {
            return [
                'finished' => true,
                'showUrl' => $showUrl,
            ];
        }

        return [
            'finished' => false,
            'showUrl' => $showUrl,
            'lobbyId' => $lobbyId,
            'initialState' => $this->ffaScoringService->getState($lobbyId, null),
            'liveStateUrl' => route('quick-game.ffa.live.state', ['lobbyId' => $lobbyId]),
            'broadcastChannel' => 'quick-game-ffa-lobby.'.$lobbyId,
            'formatLabel' => $this->formatLabel($session),
            'reverb' => $this->reverbClientConfig(),
        ];
    }

    /**
     * Stan do poll/JSON. Gdy finished — bez state, z showUrl.
     *
     * @return array{
     *     finished: bool,
     *     showUrl: string|null,
     *     message?: string,
     *     state?: array<string, mixed>
     * }
     */
    public function buildLiveState(int $lobbyId): array
    {
        $session = $this->requireSession($lobbyId);
        $showUrl = $this->finishedShowUrl($session);

        if ($session->status === QuickGameFfaSession::STATUS_FINISHED) {
            return [
                'finished' => true,
                'showUrl' => $showUrl,
                'message' => 'Mecz zakończony.',
            ];
        }

        return [
            'finished' => false,
            'showUrl' => $showUrl,
            'state' => $this->ffaScoringService->getState($lobbyId, null),
        ];
    }

    private function requireSession(int $lobbyId): QuickGameFfaSession
    {
        $session = $this->sessionRepository->findForLobby($lobbyId);
        if ($session === null) {
            throw new NotFoundHttpException('Nie znaleziono meczu FFA dla tego lobby.');
        }

        return $session;
    }

    private function finishedShowUrl(QuickGameFfaSession $session): ?string
    {
        if ($session->quick_game_id) {
            return route('games.show', ['type' => 'quick', 'id' => $session->quick_game_id]);
        }

        return null;
    }

    private function formatLabel(QuickGameFfaSession $session): string
    {
        $format = MatchFormat::fromRecord($session);
        $gameType = strtolower((string) $session->game_type);
        $prefix = $gameType === 'cricket' ? 'Krykiet' : ((int) $session->starting_score).' · FFA';

        if ($format->isSingleSet()) {
            return $prefix.' · do '.$format->legsToWinSet.' legów';
        }

        return $prefix.' · BO'.((($format->setsToWinMatch * 2) - 1)).' (sety)';
    }

    /**
     * @return array{key: string, host: string, port: int, scheme: string}
     */
    private function reverbClientConfig(): array
    {
        return [
            'key' => (string) config('broadcasting.connections.reverb.key'),
            'host' => (string) config('broadcasting.connections.reverb.client.host'),
            'port' => (int) config('broadcasting.connections.reverb.client.port'),
            'scheme' => (string) config('broadcasting.connections.reverb.client.scheme'),
        ];
    }
}
