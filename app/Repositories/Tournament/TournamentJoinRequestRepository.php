<?php

namespace App\Repositories\Tournament;

use App\Enums\TournamentJoinRequestStatus;
use App\Models\Tournament\TournamentJoinRequest;
use Illuminate\Support\Collection;

class TournamentJoinRequestRepository
{
    public function findById(int $id): ?TournamentJoinRequest
    {
        return TournamentJoinRequest::with(['user.player', 'tournament'])->find($id);
    }

    public function findPending(int $tournamentId, int $userId): ?TournamentJoinRequest
    {
        return TournamentJoinRequest::where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->where('status', TournamentJoinRequestStatus::PENDING)
            ->first();
    }

    /**
     * @return Collection<int, TournamentJoinRequest>
     */
    public function getPendingForTournament(int $tournamentId): Collection
    {
        return TournamentJoinRequest::with(['user.player'])
            ->where('tournament_id', $tournamentId)
            ->where('status', TournamentJoinRequestStatus::PENDING)
            ->orderBy('created_at')
            ->get();
    }

    public function createPending(int $tournamentId, int $userId): TournamentJoinRequest
    {
        $request = TournamentJoinRequest::create([
            'tournament_id' => $tournamentId,
            'user_id' => $userId,
            'status' => TournamentJoinRequestStatus::PENDING,
        ]);

        return $request->load(['user.player', 'tournament']);
    }

    public function reactivateAsPending(TournamentJoinRequest $request): TournamentJoinRequest
    {
        $request->update([
            'status' => TournamentJoinRequestStatus::PENDING,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);

        return $request->fresh(['user.player', 'tournament']);
    }

    public function findLatestForUser(int $tournamentId, int $userId): ?TournamentJoinRequest
    {
        return TournamentJoinRequest::where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();
    }

    public function markApproved(TournamentJoinRequest $request, int $adminId): void
    {
        $request->update([
            'status' => TournamentJoinRequestStatus::APPROVED,
            'resolved_by' => $adminId,
            'resolved_at' => now(),
        ]);
    }

    public function markRejected(TournamentJoinRequest $request, int $adminId): void
    {
        $request->update([
            'status' => TournamentJoinRequestStatus::REJECTED,
            'resolved_by' => $adminId,
            'resolved_at' => now(),
        ]);
    }
}
