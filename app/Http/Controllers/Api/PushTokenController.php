<?php

namespace App\Http\Controllers\Api;

use App\Repositories\Push\UserPushTokenRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController
{
    public function __construct(
        private UserPushTokenRepository $tokenRepository,
    ) {
    }

    /**
     * PUT /api/push-tokens
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => [
                'required',
                'string',
                'max:255',
                'regex:/^(ExponentPushToken|ExpoPushToken)\[.+\]$/',
            ],
            'platform' => 'nullable|string|in:android,ios,unknown',
            'deviceName' => 'nullable|string|max:255',
        ]);

        $this->tokenRepository->upsert(
            userId: $request->user()->id,
            expoPushToken: $validated['token'],
            platform: $validated['platform'] ?? 'unknown',
            deviceName: $validated['deviceName'] ?? null,
        );

        return response()->json([
            'message' => 'Token push zapisany',
        ]);
    }

    /**
     * DELETE /api/push-tokens
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => [
                'required',
                'string',
                'max:255',
                'regex:/^(ExponentPushToken|ExpoPushToken)\[.+\]$/',
            ],
        ]);

        $this->tokenRepository->deleteByToken(
            expoPushToken: $validated['token'],
            userId: $request->user()->id,
        );

        return response()->json([
            'message' => 'Token push usunięty',
        ]);
    }
}
