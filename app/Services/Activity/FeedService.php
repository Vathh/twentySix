<?php

namespace App\Services\Activity;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\FollowSubject;
use App\Domain\Activity\ProfileFeedAccess;
use App\Domain\Activity\ProfileFeedVisibility;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Activity\ActivityRepository;
use App\Repositories\Activity\FeedPreferenceRepository;
use App\Repositories\Activity\FollowRepository;
use App\Repositories\Activity\ProfileCircleRepository;
use App\Repositories\Friends\FriendshipRepository;
use App\Repositories\Moderation\UserBlockRepository;
use DomainException;

class FeedService
{
    public const PAGE_SIZE = 20;

    public function __construct(
        private ActivityRepository $activities,
        private FeedPreferenceRepository $preferences,
        private FollowRepository $follows,
        private FriendshipRepository $friendships,
        private ProfileCircleRepository $circle,
        private ProfileFeedAccess $profileAccess,
        private ActivityCardPresenter $cards,
        private ProfileBoardGate $board,
        private ActivityEngagementService $engagement,
        private UserBlockRepository $blocks,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, nextCursor: ?string}
     */
    public function home(User $viewer, ?string $cursor): array
    {
        $decoded = $this->cards->decodeCursor($cursor);
        $organizationsOpen = $this->preferences->channelEnabled($viewer->id, ActivityChannel::Organization);
        $page = $this->activities->homePage(
            viewerId: $viewer->id,
            friendsChannelEnabled: $this->preferences->channelEnabled($viewer->id, ActivityChannel::Friends),
            followedUserIds: $this->visibleFollowedUserIds($viewer),
            followedOrganizationIds: $organizationsOpen
                ? $this->follows->ids($viewer->id, FollowSubject::Organization)
                : [],
            blockedUserIds: $this->blocks->blockedIds($viewer->id),
            cursorAt: $decoded['at'] ?? null,
            cursorId: $decoded['id'] ?? null,
            limit: self::PAGE_SIZE,
        );

        return $this->pagePayload($page['items'], $page['hasMore'], $viewer);
    }

    /**
     * @return array{items: list<array<string, mixed>>, nextCursor: ?string}
     */
    public function profile(?User $viewer, Player $player, ?string $cursor): array
    {
        if (! $this->board->allows($viewer, $player)) {
            return ['items' => [], 'nextCursor' => null];
        }

        $ownerId = $player->user_id === null ? null : (int) $player->user_id;
        if ($viewer !== null && $ownerId !== null && $this->blocks->isBlocked((int) $viewer->id, $ownerId)) {
            return ['items' => [], 'nextCursor' => null];
        }

        $decoded = $this->cards->decodeCursor($cursor);
        $page = $this->activities->profilePage(
            playerId: $player->id,
            cursorAt: $decoded['at'] ?? null,
            cursorId: $decoded['id'] ?? null,
            limit: self::PAGE_SIZE,
        );

        return $this->pagePayload($page['items'], $page['hasMore'], $viewer);
    }

    /**
     * @return array{
     *     profileFeedVisibility: string,
     *     channels: array{friends: bool, organization: bool}
     * }
     */
    public function preferences(User $user): array
    {
        return [
            'profileFeedVisibility' => $this->preferences->visibility($user)->value,
            'channels' => [
                'friends' => $this->preferences->channelEnabled($user->id, ActivityChannel::Friends),
                'organization' => $this->preferences->channelEnabled($user->id, ActivityChannel::Organization),
            ],
        ];
    }

    public function updatePreferences(
        User $user,
        ProfileFeedVisibility $visibility,
        bool $friendsChannel,
        bool $organizationChannel,
    ): void {
        $this->preferences->setVisibility($user, $visibility);
        $this->preferences->setChannel($user->id, ActivityChannel::Friends, $friendsChannel);
        $this->preferences->setChannel($user->id, ActivityChannel::Organization, $organizationChannel);
    }

    /**
     * @return array{allowed: bool, following: bool}
     */
    public function followState(User $viewer, FollowSubject $subject, string $key): array
    {
        $allowed = $this->canFollow($viewer, $subject, $key);

        return [
            'allowed' => $allowed,
            'following' => $allowed && $this->follows->isFollowing($viewer->id, $subject, (int) $key),
        ];
    }

    public function follow(User $viewer, FollowSubject $subject, string $key): void
    {
        if (! $this->canFollow($viewer, $subject, $key)) {
            throw new DomainException($this->followDeniedMessage($viewer, $subject, $key));
        }

        $this->follows->follow($viewer->id, $subject, (int) $key);
    }

    public function unfollow(User $viewer, FollowSubject $subject, string $key): void
    {
        if (! ctype_digit($key)) {
            return;
        }

        $this->follows->unfollow($viewer->id, $subject, (int) $key);
    }

    /**
     * Obserwowani, których tablicę profilu wolno oglądać.
     *
     * @return list<int>
     */
    private function visibleFollowedUserIds(User $viewer): array
    {
        $ids = $this->follows->ids($viewer->id, FollowSubject::User);
        if ($ids === []) {
            return [];
        }

        $friends = array_fill_keys($this->friendships->friendIds($viewer->id), true);
        $visible = [];

        foreach (User::query()->whereIn('id', $ids)->get() as $user) {
            $visibility = $user->profile_feed_visibility instanceof ProfileFeedVisibility
                ? $user->profile_feed_visibility
                : (ProfileFeedVisibility::tryFrom((string) $user->profile_feed_visibility) ?? ProfileFeedVisibility::Public);
            $areFriends = isset($friends[$user->id]);
            $sharesCircle = $visibility === ProfileFeedVisibility::FriendsAndCircle
                && ! $areFriends
                && $this->circle->shares($viewer->id, $user->id);

            if ($this->profileAccess->canView($viewer->id, $user->id, $visibility, $areFriends, $sharesCircle)) {
                $visible[] = (int) $user->id;
            }
        }

        return $visible;
    }

    private function canFollow(User $viewer, FollowSubject $subject, string $key): bool
    {
        if (! ctype_digit($key)) {
            return false;
        }

        $id = (int) $key;

        return match ($subject) {
            FollowSubject::User => $id !== $viewer->id && User::query()->whereKey($id)->exists(),
            FollowSubject::Organization => Organization::query()->whereKey($id)->exists(),
        };
    }

    private function followDeniedMessage(User $viewer, FollowSubject $subject, string $key): string
    {
        if ($subject === FollowSubject::User && ctype_digit($key) && (int) $key === $viewer->id) {
            return 'Nie możesz obserwować siebie.';
        }

        return $subject === FollowSubject::Organization
            ? 'Nie ma takiej organizacji.'
            : 'Nie ma takiego gracza.';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Activity\Activity>  $items
     * @return array{items: list<array<string, mixed>>, nextCursor: ?string}
     */
    private function pagePayload($items, bool $hasMore, ?User $viewer): array
    {
        $overlay = $this->engagement->overlay($items, $viewer);
        $cards = $items
            ->map(fn ($activity) => $this->cards->present($activity, $overlay[(int) $activity->id] ?? []))
            ->values()
            ->all();
        $last = $items->last();

        return [
            'items' => $cards,
            'nextCursor' => $hasMore && $last !== null ? $this->cards->cursor($last) : null,
        ];
    }
}
