<?php

namespace App\Http\Controllers\Api;

use App\Models\Users\User;
use App\Services\Training\TrainingEventAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingEventController
{
    public function __construct(
        private TrainingEventAttemptService $attempts,
    ) {}

    public function start(Request $request, int $definition): JsonResponse
    {
        $request->validate([
            'clientAttemptId' => ['required', 'uuid'],
        ]);

        $result = $this->attempts->start(
            $this->user($request),
            $definition,
            (string) $request->input('clientAttemptId'),
        );

        return response()->json($result, 201);
    }

    public function current(Request $request, int $definition): JsonResponse
    {
        return response()->json($this->attempts->current($this->user($request), $definition));
    }

    public function complete(Request $request, int $definition, string $clientAttempt): JsonResponse
    {
        $request->validate([
            'completedAt' => ['required', 'date'],
            'score' => ['nullable', 'integer', 'between:-1000000,1000000'],
            'stats' => ['required', 'array'],
            'items' => ['nullable', 'array', 'max:80'],
        ]);

        $payload = $request->all();
        $payload['clientAttemptId'] = $clientAttempt;

        return response()->json($this->attempts->complete($this->user($request), $definition, $payload));
    }

    public function abort(Request $request, int $definition, string $clientAttempt): JsonResponse
    {
        return response()->json(
            $this->attempts->abort($this->user($request), $definition, $clientAttempt),
        );
    }

    public function classification(Request $request, int $definition): JsonResponse
    {
        return response()->json($this->attempts->classification($this->user($request), $definition));
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
