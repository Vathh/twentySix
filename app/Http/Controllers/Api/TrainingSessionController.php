<?php

namespace App\Http\Controllers\Api;

use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use App\Services\Training\TrainingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingSessionController
{
    public function __construct(
        private TrainingSessionService $sessions,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'clientSessionId' => ['required', 'uuid'],
            'definitionId' => ['nullable', 'integer', 'min:1'],
            'exerciseKey' => ['nullable', 'string', 'max:64'],
            'completedAt' => ['required', 'date'],
            'score' => ['nullable', 'integer', 'between:-1000000,1000000'],
            'stats' => ['required', 'array'],
        ]);

        $session = $this->sessions->complete($this->user($request), $request->all());

        return response()->json($this->payload($session), 201);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TrainingSession $session): array
    {
        return [
            'id' => $session->id,
            'clientSessionId' => $session->client_session_id,
            'definitionId' => $session->definition_id,
            'exerciseKey' => $session->exercise_key,
            'kind' => $session->kind->value,
            'completedAt' => $session->completed_at?->toIso8601String(),
            'score' => $session->score,
            'stats' => $session->stats === [] ? (object) [] : $session->stats,
        ];
    }
}
