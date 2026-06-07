<?php

namespace App\Http\Controllers\Api;

use App\Services\Tournament\TournamentInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentInvitationController
{
    public function __construct(
        private TournamentInvitationService $invitationService,
    ) {
    }

    /**
     * GET /api/tournaments/invitations/received
     */
    public function received(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $invitations = $this->invitationService->getReceivedForUser($userId);

        return response()->json([
            'invitations' => $invitations->map(fn ($invitation) => $this->formatInvitation($invitation)),
        ]);
    }

    /**
     * POST /api/tournaments/invitations/{invitationId}/accept
     */
    public function accept(Request $request, int $invitationId): JsonResponse
    {
        try {
            $this->invitationService->accept($invitationId, $request->user()->id);

            return response()->json(['message' => 'Zaproszenie zostało zaakceptowane']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/tournaments/invitations/{invitationId}/reject
     */
    public function reject(Request $request, int $invitationId): JsonResponse
    {
        try {
            $this->invitationService->reject($invitationId, $request->user()->id);

            return response()->json(['message' => 'Zaproszenie zostało odrzucone']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/tournaments/invitations/{invitationId}/withdraw
     */
    public function withdraw(Request $request, int $invitationId): JsonResponse
    {
        try {
            $this->invitationService->withdraw($invitationId, $request->user()->id);

            return response()->json(['message' => 'Wycofano udział w turnieju']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    private function formatInvitation($invitation): array
    {
        return [
            'id' => $invitation->id,
            'tournamentId' => $invitation->tournamentId,
            'tournamentName' => $invitation->tournamentName,
            'status' => $invitation->status->value,
            'statusLabel' => $invitation->status->label(),
            'playerName' => $invitation->userPlayer?->name ?? 'Brak nazwy',
            'createdAt' => $invitation->createdAt->toIso8601String(),
        ];
    }
}
