<?php

namespace App\Http\Controllers\Api;

use App\Models\Users\User;
use App\Services\Training\TrainingHistoryWipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingHistoryWipeController
{
    public function __construct(
        private TrainingHistoryWipeService $wipes,
    ) {}

    public function destroy(Request $request): JsonResponse
    {
        $result = $this->wipes->wipeForUser($this->user($request));

        return response()->json($result);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
