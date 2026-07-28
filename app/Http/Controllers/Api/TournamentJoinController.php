<?php

namespace App\Http\Controllers\Api;

use App\Services\Tournament\TournamentJoinRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentJoinController
{
    public function __construct(
        private TournamentJoinRequestService $joinRequestService,
    ) {
    }

    public function preview(Request $request, string $code): JsonResponse
    {
        try {
            $userId = $request->user()?->id;
            $preview = $this->joinRequestService->previewForUser($code, $userId);

            return response()->json($preview);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function apply(Request $request, string $code): JsonResponse
    {
        try {
            $joinRequest = $this->joinRequestService->apply($code, $request->user());

            return response()->json([
                'message' => 'Zgłoszenie wysłane — czekaj na akceptację organizatora.',
                'requestId' => $joinRequest->id,
                'status' => $joinRequest->status->value,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
