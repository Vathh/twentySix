<?php

namespace App\Domain\Activity;

final class ProfileFeedAccess
{
    public function canView(
        ?int $viewerUserId,
        ?int $ownerUserId,
        ProfileFeedVisibility $visibility,
        bool $areFriends,
        bool $sharesCircle,
    ): bool {
        if ($ownerUserId !== null && $viewerUserId === $ownerUserId) {
            return true;
        }

        return match ($visibility) {
            ProfileFeedVisibility::Public => true,
            ProfileFeedVisibility::Friends => $areFriends,
            ProfileFeedVisibility::FriendsAndCircle => $areFriends || $sharesCircle,
        };
    }
}
