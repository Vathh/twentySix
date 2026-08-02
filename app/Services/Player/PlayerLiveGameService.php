<?php

namespace App\Services\Player;

use App\Domain\Player\LiveGameDomain;
use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Repositories\Game\GameRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\QuickGame\QuickGameFfaSessionRepository;
use App\Repositories\QuickGame\QuickGameRepository;

class PlayerLiveGameService
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private QuickGameRepository $quickGameRepository,
        private QuickGameFfaSessionRepository $quickGameFfaSessionRepository,
        private PlayerRepository $playerRepository,
    ) {
    }

    /**
     * Mecze w trakcie z linkiem do webowego podglądu live
     * (H2H turniej/quick + FFA po lobbyId).
     *
     * @return list<array{
     *     type: string,
     *     id: int,
     *     opponentName: string,
     *     tournamentName: string|null,
     *     stageLabel: string,
     *     liveUrl: string
     * }>
     */
    public function findLiveGamesForPlayer(int $playerId): array
    {
        $group = $this->gameRepository
            ->findInProgressForPlayer($playerId)
            ->map(fn (Game $game) => $this->mapH2h(
                type: 'group',
                id: (int) $game->id,
                playerId: $playerId,
                player1Name: $game->player1?->name,
                player2Name: $game->player2?->name,
                player1Id: (int) $game->player1_id,
                tournamentName: $game->tournament?->name,
                stageLabel: 'Grupa',
            ));

        $playoff = $this->playoffGameRepository
            ->findInProgressForPlayer($playerId)
            ->map(fn (PlayoffGame $game) => $this->mapH2h(
                type: 'playoff',
                id: (int) $game->id,
                playerId: $playerId,
                player1Name: $game->player1?->name,
                player2Name: $game->player2?->name,
                player1Id: (int) $game->player1_id,
                tournamentName: $game->tournament?->name,
                stageLabel: 'Play-off',
            ));

        $quick = $this->quickGameRepository
            ->findInProgressForPlayer($playerId)
            ->map(fn (QuickGame $game) => $this->mapH2h(
                type: 'quick',
                id: (int) $game->id,
                playerId: $playerId,
                player1Name: $game->player1?->name,
                player2Name: $game->player2?->name,
                player1Id: (int) $game->player1_id,
                tournamentName: null,
                stageLabel: 'Szybki mecz',
            ));

        $ffa = $this->quickGameFfaSessionRepository
            ->findInProgressContainingPlayer($playerId)
            ->map(fn (QuickGameFfaSession $session) => $this->mapFfa($session, $playerId));

        return $group
            ->concat($playoff)
            ->concat($quick)
            ->concat($ffa)
            ->map(fn (LiveGameDomain $liveGame) => $liveGame->toArray())
            ->values()
            ->all();
    }

    private function mapH2h(
        string $type,
        int $id,
        int $playerId,
        ?string $player1Name,
        ?string $player2Name,
        int $player1Id,
        ?string $tournamentName,
        string $stageLabel,
    ): LiveGameDomain {
        return new LiveGameDomain(
            type: $type,
            id: $id,
            opponentName: LiveGameDomain::resolveOpponentName($playerId, $player1Id, $player1Name, $player2Name),
            tournamentName: $tournamentName,
            stageLabel: $stageLabel,
            liveUrl: route('games.live', ['type' => $type, 'id' => $id]),
        );
    }

    private function mapFfa(QuickGameFfaSession $session, int $playerId): LiveGameDomain
    {
        $order = array_map('intval', $session->player_order ?? []);
        $opponentIds = array_values(array_filter($order, fn (int $id) => $id !== $playerId));
        $names = $this->playerRepository->getNamesByIds($opponentIds);

        $opponentNames = [];
        foreach ($opponentIds as $id) {
            $opponentNames[] = $names[$id] ?? '—';
        }

        return new LiveGameDomain(
            type: 'ffa',
            id: (int) $session->lobby_id,
            opponentName: implode(', ', $opponentNames) ?: '—',
            tournamentName: null,
            stageLabel: 'Szybki mecz · FFA',
            liveUrl: route('quick-game.ffa.live', ['lobbyId' => $session->lobby_id]),
        );
    }
}
