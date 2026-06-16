<?php

namespace App\Repositories\QuickGame;

use App\DTO\QuickGameFfa\RecordFfaVisitDTO;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Models\QuickGame\QuickGameFfaVisit;
use Illuminate\Support\Collection;

class QuickGameFfaVisitRepository
{
    public function findByClientVisitId(string $clientVisitId): ?QuickGameFfaVisit
    {
        return QuickGameFfaVisit::where('client_visit_id', $clientVisitId)->first();
    }

    public function getActiveForSession(QuickGameFfaSession $session): Collection
    {
        return QuickGameFfaVisit::where('ffa_session_id', $session->id)
            ->where('is_voided', false)
            ->orderBy('leg_number')
            ->orderBy('visit_number')
            ->orderBy('id')
            ->get();
    }

    public function getActiveForLeg(QuickGameFfaSession $session, int $legNumber): Collection
    {
        return QuickGameFfaVisit::where('ffa_session_id', $session->id)
            ->where('leg_number', $legNumber)
            ->where('is_voided', false)
            ->orderBy('visit_number')
            ->orderBy('id')
            ->get();
    }

    public function nextVisitNumber(QuickGameFfaSession $session, int $legNumber): int
    {
        $max = QuickGameFfaVisit::where('ffa_session_id', $session->id)
            ->where('leg_number', $legNumber)
            ->max('visit_number');

        return ($max ?? 0) + 1;
    }

    public function create(QuickGameFfaSession $session, int $legNumber, RecordFfaVisitDTO $dto): QuickGameFfaVisit
    {
        return QuickGameFfaVisit::create([
            'ffa_session_id' => $session->id,
            'leg_number' => $legNumber,
            'player_id' => $dto->playerId,
            'visit_number' => $this->nextVisitNumber($session, $legNumber),
            'score' => $dto->score,
            'remaining_before' => $dto->remainingBefore,
            'remaining_after' => $dto->remainingAfter,
            'darts_in_visit' => $dto->dartsInVisit,
            'closed_leg' => $dto->closedLeg,
            'bust' => $dto->bust,
            'is_voided' => false,
            'client_visit_id' => $dto->clientVisitId,
        ]);
    }

    public function updateFromDto(QuickGameFfaVisit $visit, RecordFfaVisitDTO $dto): QuickGameFfaVisit
    {
        $visit->update([
            'score' => $dto->score,
            'remaining_after' => $dto->remainingAfter,
            'darts_in_visit' => $dto->dartsInVisit,
            'closed_leg' => $dto->closedLeg,
            'bust' => $dto->bust,
        ]);

        return $visit->fresh();
    }

    public function voidLastForLeg(QuickGameFfaSession $session, int $legNumber): ?QuickGameFfaVisit
    {
        $visit = QuickGameFfaVisit::where('ffa_session_id', $session->id)
            ->where('leg_number', $legNumber)
            ->where('is_voided', false)
            ->orderByDesc('visit_number')
            ->orderByDesc('id')
            ->first();

        if ($visit === null) {
            return null;
        }

        $visit->update(['is_voided' => true]);

        return $visit;
    }
}
