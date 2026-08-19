<?php

namespace App\Http\Controllers\Api;

use App\Services\Organization\OrganizationInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationInvitationController
{
    public function __construct(
        private OrganizationInvitationService $invitationService,
    ) {
    }

    /**
     * GET /api/organizations/invitations/received
     */
    public function received(Request $request): JsonResponse
    {
        $invitations = $this->invitationService->getReceivedForUser($request->user()->id);

        return response()->json([
            'invitations' => $invitations->map(fn ($invitation) => $this->formatInvitation($invitation)),
        ]);
    }

    /**
     * POST /api/organizations/invitations/{invitationId}/accept
     */
    public function accept(Request $request, int $invitationId): JsonResponse
    {
        try {
            $this->invitationService->accept($invitationId, $request->user()->id);

            return response()->json(['message' => 'Zaproszenie do organizacji zostało zaakceptowane']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/organizations/invitations/{invitationId}/reject
     */
    public function reject(Request $request, int $invitationId): JsonResponse
    {
        try {
            $this->invitationService->reject($invitationId, $request->user()->id);

            return response()->json(['message' => 'Zaproszenie do organizacji zostało odrzucone']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    private function formatInvitation($invitation): array
    {
        return [
            'id' => $invitation->id,
            'type' => 'organization',
            'organizationId' => $invitation->organizationId,
            'organizationName' => $invitation->organizationName,
            'status' => $invitation->status->value,
            'statusLabel' => $invitation->status->label(),
            'playerName' => $invitation->userPlayer?->name ?? 'Brak nazwy',
            'createdAt' => $invitation->createdAt->toIso8601String(),
        ];
    }
}
