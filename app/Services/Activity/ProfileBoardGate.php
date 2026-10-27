<?php

namespace App\Services\Activity;

use App\Domain\Activity\ProfileFeedAccess;
use App\Domain\Activity\ProfileFeedVisibility;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Activity\FeedPreferenceRepository;
use App\Repositories\Activity\ProfileCircleRepository;
use App\Repositories\Friends\FriendshipRepository;

class ProfileBoardGate
{
    public function __construct(
        private ProfileFeedAccess $access,
        private FeedPreferenceRepository $preferences,
        private FriendshipRepository $friendships,
        private ProfileCircleRepository $circles,
    ) {}

    public function allows(?User $viewer, Player $player): bool
    {
        $owner = $player->relationLoaded('user') ? $player->user : $player->user()->first();
        $visibility = $owner === null
            ? ProfileFeedVisibility::Public
            : $this->preferences->visibility($owner);

        $viewerId = $viewer?->id;
        $ownerId = $owner?->id;

        return $this->access->canView(
            $viewerId,
            $ownerId,
            $visibility,
            $viewerId !== null && $ownerId !== null && $this->friendships->areFriends($viewerId, $ownerId),
            $viewerId !== null && $ownerId !== null && $this->circles->shares($viewerId, $ownerId),
        );
    }
}
