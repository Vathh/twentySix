<?php

namespace App\Http\Controllers\Api;

use App\Models\Users\User;
use App\Services\Training\TrainingActivityService;
use App\Services\Training\TrainingExerciseCountService;
use App\Services\Training\TrainingMedalsService;
use App\Services\Training\TrainingRecordsService;
use App\Services\Training\TrainingSkillStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingProgressController
{
    public function __construct(
        private TrainingRecordsService $records,
        private TrainingMedalsService $medals,
        private TrainingExerciseCountService $exerciseCount,
        private TrainingActivityService $activity,
        private TrainingSkillStatsService $skillStats,
    ) {}

    public function records(Request $request): JsonResponse
    {
        return response()->json($this->records->bundle($this->user($request)));
    }

    public function medals(Request $request): JsonResponse
    {
        return response()->json([
            'medals' => $this->medals->forUser($this->user($request)),
        ]);
    }

    public function analysis(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'completedExerciseCount' => $this->exerciseCount->forUser($user),
            'medals' => $this->medals->forUser($user),
            'activity' => $this->activity->forUser($user, TrainingActivityService::DEFAULT_DAYS),
        ]);
    }

    public function skillStats(Request $request): JsonResponse
    {
        $window = (string) $request->query('window', '90d');
        $offset = max(0, (int) $request->query('offset', 0));

        return response()->json(
            $this->skillStats->skillSeries($this->user($request), $window, $offset),
        );
    }

    public function sectorStats(Request $request): JsonResponse
    {
        $offset = max(0, (int) $request->query('offset', 0));

        return response()->json(
            $this->skillStats->sectorAims($this->user($request), $offset),
        );
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
