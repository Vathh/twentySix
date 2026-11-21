<?php

namespace App\Http\Controllers\Api;

use App\Models\Users\User;
use App\Services\Training\TrainingChallengeRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingChallengeController
{
    public function __construct(
        private TrainingChallengeRankingService $challenges,
    ) {}

    public function publish(Request $request, int $session): JsonResponse
    {
        $result = $this->challenges->publish($this->user($request), $session);

        return response()->json($result);
    }

    public function withdraw(Request $request, int $definition): JsonResponse
    {
        $result = $this->challenges->withdraw($this->user($request), $definition);

        return response()->json($result);
    }

    public function ranking(Request $request, int $definition): JsonResponse
    {
        $offset = (int) $request->query('offset', 0);
        $limit = (int) $request->query('limit', 10);
        $result = $this->challenges->ranking($this->user($request), $definition, $offset, $limit);

        return response()->json($result);
    }

    public function attempts(Request $request, int $definition): JsonResponse
    {
        $sort = (string) $request->query('sort', 'newest');
        if ($sort !== 'best' && $sort !== 'newest') {
            $sort = 'newest';
        }
        $days = $request->query('days');
        $daysInt = is_numeric($days) ? (int) $days : null;
        $beforeCompletedAt = $request->query('beforeCompletedAt');
        $beforeId = $request->query('beforeId');

        $result = $this->challenges->attempts(
            $this->user($request),
            $definition,
            $sort,
            $daysInt,
            is_string($beforeCompletedAt) ? $beforeCompletedAt : null,
            is_numeric($beforeId) ? (int) $beforeId : null,
        );

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
