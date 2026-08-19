<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\League\LeagueGamePlayService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueGamePlayController extends Controller
{
    public function __construct(
        private LeagueGamePlayService $leagueGamePlayService,
    ) {
    }

    public function mine(Request $request): JsonResponse
    {
        return $this->ok(fn () => $this->leagueGamePlayService->mine($request->user()));
    }

    public function invitations(Request $request): JsonResponse
    {
        return $this->ok(fn () => $this->leagueGamePlayService->invitations($request->user()));
    }

    public function show(Request $request, int $leagueGame): JsonResponse
    {
        return $this->ok(fn () => $this->leagueGamePlayService->show($request->user(), $leagueGame));
    }

    public function openLobby(Request $request, int $leagueGame): JsonResponse
    {
        return $this->ok(fn () => $this->leagueGamePlayService->openLobby($request->user(), $leagueGame));
    }

    public function accept(Request $request, int $leagueGame): JsonResponse
    {
        return $this->ok(fn () => $this->leagueGamePlayService->accept($request->user(), $leagueGame));
    }

    public function reject(Request $request, int $leagueGame): JsonResponse
    {
        return $this->ok(fn () => $this->leagueGamePlayService->reject($request->user(), $leagueGame));
    }

    public function cancel(Request $request, int $leagueGame): JsonResponse
    {
        return $this->ok(fn () => $this->leagueGamePlayService->cancel($request->user(), $leagueGame));
    }

    public function start(Request $request, int $leagueGame): JsonResponse
    {
        return $this->ok(fn () => $this->leagueGamePlayService->start($request->user(), $leagueGame));
    }

    private function ok(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
