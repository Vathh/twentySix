<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\User\UserCompetitionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyCompetitionsController extends Controller
{
    public function __construct(
        private UserCompetitionsService $userCompetitionsService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->userCompetitionsService->forUser($request->user()),
        );
    }
}
