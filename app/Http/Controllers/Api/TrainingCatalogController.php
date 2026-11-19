<?php

namespace App\Http\Controllers\Api;

use App\Models\Users\User;
use App\Services\Training\TrainingCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingCatalogController
{
    public function __construct(
        private TrainingCatalogService $catalog,
    ) {}

    public function index(Request $request, string $kind): JsonResponse
    {
        return response()->json([
            'items' => $this->catalog->list($this->user($request), $kind),
        ]);
    }

    public function show(Request $request, int $definition): JsonResponse
    {
        return response()->json($this->catalog->show($this->user($request), $definition));
    }

    public function showExercise(Request $request, string $exercise): JsonResponse
    {
        return response()->json($this->catalog->showExercise($this->user($request), $exercise));
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
