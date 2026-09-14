<?php

namespace App\Http\Controllers\Api;

use App\Services\Season\SeasonInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonInvitationController
{
    public function __construct(
        private SeasonInvitationService $invitationService,
    ) {}

    /**
     * GET /api/seasons/invitations/received
     */
    public function received(Request $request): JsonResponse
    {
        $invitations = $this->invitationService->getReceivedForUser($request->user()->id);

        return response()->json([
            'invitations' => $invitations->map(fn ($invitation) => $this->formatInvitation($invitation)),
        ]);
    }

    /**
     * POST /api/seasons/invitations/{invitationId}/accept
     */
    public function accept(Request $request, int $invitationId): JsonResponse
    {
        try {
            $this->invitationService->accept($invitationId, $request->user()->id);

            return response()->json(['message' => 'Zaproszenie do sezonu zostało zaakceptowane']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/seasons/invitations/{invitationId}/reject
     */
    public function reject(Request $request, int $invitationId): JsonResponse
    {
        try {
            $this->invitationService->reject($invitationId, $request->user()->id);

            return response()->json(['message' => 'Zaproszenie do sezonu zostało odrzucone']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    private function formatInvitation($invitation): array
    {
        return [
            'id' => $invitation->id,
            'type' => 'season',
            'seasonId' => $invitation->seasonId,
            'seasonName' => $invitation->seasonName,
            'status' => $invitation->status->value,
            'statusLabel' => $invitation->status->label(),
            'playerName' => $invitation->userPlayer->name ?? 'Brak nazwy',
            'createdAt' => $invitation->createdAt->toIso8601String(),
        ];
    }
}
