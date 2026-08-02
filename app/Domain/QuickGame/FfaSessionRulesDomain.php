<?php

namespace App\Domain\QuickGame;

use App\Models\QuickGame\QuickGameFfaPresence;
use DomainException;

/**
 * Reguły stanu sesji FFA niezwiązane z rotacją tur: walkower przy opuszczeniu meczu,
 * efektywny status obecności, filtrowanie graczy śledzonych heartbeatem.
 *
 * Czyste reguły domenowe — bez Eloquent, bez HTTP (poza stałymi statusów z modelu).
 */
final class FfaSessionRulesDomain
{
    /**
     * 2-osobowy each_own kończy się walkowerem natychmiast po opuszczeniu meczu
     * przez jednego z graczy — nie ma z kim kontynuować.
     */
    public static function shouldForfeitOnLeave(string $scoringMode, int $playerCount, bool $isInProgress): bool
    {
        return $scoringMode === 'each_own' && $playerCount === 2 && $isInProgress;
    }

    /**
     * @param  array<int, int>  $playerIds
     */
    public static function resolveForfeitWinnerId(array $playerIds, int $leavingPlayerId): int
    {
        foreach ($playerIds as $playerId) {
            if ($playerId !== $leavingPlayerId) {
                return $playerId;
            }
        }

        throw new DomainException('Nie można ustalić zwycięzcy walkoweru.');
    }

    /**
     * Mecz 3+ graczy jest rozstrzygnięty walkowerem, gdy zostaje mniej niż 2 aktywnych graczy.
     *
     * @param  array<int, int>  $activePlayerIds
     */
    public static function isDecidedByForfeit(array $activePlayerIds): bool
    {
        return count($activePlayerIds) < 2;
    }

    /**
     * @param  array<int, int>  $activePlayerIds
     */
    public static function soleRemainingPlayerId(array $activePlayerIds): ?int
    {
        return $activePlayerIds[0] ?? null;
    }

    /**
     * ID graczy śledzonych heartbeatem (bez gości lokalnych bez konta).
     *
     * @param  array<int, int>  $playerIds
     * @param  array<int, int>  $guestPlayerIds
     * @return array<int, int>
     */
    public static function heartbeatTrackedPlayerIds(array $playerIds, array $guestPlayerIds): array
    {
        if ($playerIds === []) {
            return [];
        }

        return array_values(array_diff($playerIds, $guestPlayerIds));
    }

    /**
     * Goście lokalni bez konta nie łączą się z apką — zawsze traktuj jako connected,
     * niezależnie od zapisanego (nieaktualizowanego) statusu.
     */
    public static function effectivePresenceStatus(bool $isGuestWithoutAccount, string $status): string
    {
        if ($isGuestWithoutAccount && $status === QuickGameFfaPresence::STATUS_DISCONNECTED) {
            return QuickGameFfaPresence::STATUS_CONNECTED;
        }

        return $status;
    }
}
