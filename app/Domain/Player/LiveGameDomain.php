<?php

namespace App\Domain\Player;

/**
 * Trwający mecz gracza z linkiem do podglądu live (H2H turniej/quick + FFA).
 */
class LiveGameDomain
{
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $opponentName,
        public readonly ?string $tournamentName,
        public readonly string $stageLabel,
        public readonly string $liveUrl,
    ) {
    }

    /** Przeciwnik w meczu H2H to gracz inny niż $playerId spośród player1/player2. */
    public static function resolveOpponentName(
        int $playerId,
        int $player1Id,
        ?string $player1Name,
        ?string $player2Name,
    ): string {
        $name = $player1Id === $playerId ? $player2Name : $player1Name;

        return $name ?: '—';
    }

    /**
     * @return array{type: string, id: int, opponentName: string, tournamentName: string|null, stageLabel: string, liveUrl: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'opponentName' => $this->opponentName,
            'tournamentName' => $this->tournamentName,
            'stageLabel' => $this->stageLabel,
            'liveUrl' => $this->liveUrl,
        ];
    }
}
