<?php

namespace App\Repositories\Webcam;

use App\Enums\WebcamMatchStatus;
use App\Models\Webcam\WebcamMatch;
use App\Models\Webcam\WebcamMatchVisit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class WebcamMatchRepository
{
    public function create(int $hostUserId, int $guestUserId): WebcamMatch
    {
        return WebcamMatch::query()->create([
            'host_user_id' => $hostUserId,
            'guest_user_id' => $guestUserId,
            'status' => WebcamMatchStatus::Lobby,
            'leg_number' => 1,
        ]);
    }

    public function findWithPlayers(int $id): ?WebcamMatch
    {
        return WebcamMatch::query()
            ->with(['host.player', 'guest.player', 'visits'])
            ->find($id);
    }

    public function lock(int $id): WebcamMatch
    {
        $match = WebcamMatch::query()->whereKey($id)->lockForUpdate()->first();
        if ($match === null) {
            throw (new ModelNotFoundException)->setModel(WebcamMatch::class, [$id]);
        }

        return $match->load(['host.player', 'guest.player', 'visits']);
    }

    public function unfinishedBetween(int $userA, int $userB): ?WebcamMatch
    {
        return WebcamMatch::query()
            ->whereIn('status', [WebcamMatchStatus::Lobby, WebcamMatchStatus::Live])
            ->where(function ($query) use ($userA, $userB) {
                $query->where(function ($pair) use ($userA, $userB) {
                    $pair->where('host_user_id', $userA)->where('guest_user_id', $userB);
                })->orWhere(function ($pair) use ($userA, $userB) {
                    $pair->where('host_user_id', $userB)->where('guest_user_id', $userA);
                });
            })
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, WebcamMatch>
     */
    public function listForUser(int $userId): Collection
    {
        return WebcamMatch::query()
            ->with(['host.player', 'guest.player'])
            ->where(function ($query) use ($userId) {
                $query->where('host_user_id', $userId)->orWhere('guest_user_id', $userId);
            })
            ->orderByRaw("case status when 'live' then 0 when 'lobby' then 1 else 2 end")
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    public function isParticipant(int $matchId, int $userId): bool
    {
        return WebcamMatch::query()
            ->whereKey($matchId)
            ->where(function ($query) use ($userId) {
                $query->where('host_user_id', $userId)->orWhere('guest_user_id', $userId);
            })
            ->exists();
    }

    public function save(WebcamMatch $match): void
    {
        $match->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addVisit(WebcamMatch $match, array $attributes): WebcamMatchVisit
    {
        return $match->visits()->create($attributes);
    }

    public function deleteVisit(WebcamMatchVisit $visit): void
    {
        $visit->delete();
    }
}
