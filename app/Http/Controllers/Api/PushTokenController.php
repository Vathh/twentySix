<?php

namespace App\Http\Controllers\Api;

use App\Services\Push\PushTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController
{
    public function __construct(
        private PushTokenService $pushTokenService,
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

        $this->pushTokenService->upsert(
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

        $this->pushTokenService->deleteForUser(
            userId: $request->user()->id,
            expoPushToken: $validated['token'],
        );

        return response()->json([
            'message' => 'Token push usunięty',
        ]);
    }
}
