<?php

namespace App\Http\Controllers\Api;

use App\Models\Users\User;
use App\Services\Training\TrainingActivityService;
use App\Services\Training\TrainingExerciseCountService;
use App\Services\Training\TrainingGoalsService;
use App\Services\Training\TrainingRecordsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingProgressController
{
    public function __construct(
        private TrainingRecordsService $records,
        private TrainingGoalsService $goals,
        private TrainingExerciseCountService $exerciseCount,
        private TrainingActivityService $activity,
    ) {}

    public function records(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->records->forUser($this->user($request)),
        ]);
    }

    public function goals(Request $request): JsonResponse
    {
        return response()->json($this->goals->forUser($this->user($request)));
    }

    public function analysis(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $progress = $this->goals->forUser($user);

        return response()->json([
            'completedExerciseCount' => $this->exerciseCount->forUser($user),
            'records' => $this->records->forUser($user),
            'goals' => $progress['goals'],
            'badges' => $progress['badges'],
            'medals' => $progress['medals'],
            'activity' => $this->activity->forUser($user, TrainingActivityService::DEFAULT_DAYS),
        ]);
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
