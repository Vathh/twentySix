<?php

namespace App\Services\Activity;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\FeedMuteSubject;
use App\Domain\Activity\ProfileFeedVisibility;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Activity\ActivityRepository;
use App\Repositories\Activity\FeedPreferenceRepository;
use App\Repositories\Friends\FriendshipRepository;
use App\Repositories\Organization\OrganizationRepository;
use DomainException;

class FeedService
{
    public const PAGE_SIZE = 20;

    public function __construct(
        private ActivityRepository $activities,
        private FeedPreferenceRepository $preferences,
        private FriendshipRepository $friendships,
        private OrganizationRepository $organizations,
        private ActivityCardPresenter $cards,
        private ProfileBoardGate $board,
        private ActivityEngagementService $engagement,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, nextCursor: ?string}
     */
    public function home(User $viewer, ?string $cursor): array
    {
        $decoded = $this->cards->decodeCursor($cursor);
        $page = $this->activities->homePage(
            viewerId: $viewer->id,
            friendsChannelEnabled: $this->preferences->channelEnabled($viewer->id, ActivityChannel::Friends),
            organizationChannelEnabled: $this->preferences->channelEnabled($viewer->id, ActivityChannel::Organization),
            friendIds: $this->friendships->friendIds($viewer->id),
            organizationIds: $this->organizations->relatedOrganizationIds($viewer->id),
            mutedUserIds: $this->preferences->mutedIds($viewer->id, FeedMuteSubject::User),
            mutedOrganizationIds: $this->preferences->mutedIds($viewer->id, FeedMuteSubject::Organization),
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
     *     channels: array{friends: bool, organization: bool},
     *     mutes: list<array{subjectType: string, subjectKey: string, label: string}>
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
            'mutes' => $this->preferences->mutedList($user->id),
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
     * @return array{allowed: bool, muted: bool}
     */
    public function muteState(User $viewer, FeedMuteSubject $subject, string $key): array
    {
        return [
            'allowed' => $this->canMute($viewer, $subject, $key),
            'muted' => $this->preferences->isMuted($viewer->id, $subject, $key),
        ];
    }

    public function mute(User $viewer, FeedMuteSubject $subject, string $key): void
    {
        if (! $this->canMute($viewer, $subject, $key)) {
            throw new DomainException($this->muteDeniedMessage($viewer, $subject, $key));
        }

        $this->preferences->mute($viewer->id, $subject, $key);
    }

    public function unmute(User $viewer, FeedMuteSubject $subject, string $key): void
    {
        $this->preferences->unmute($viewer->id, $subject, $key);
    }

    private function canMute(User $viewer, FeedMuteSubject $subject, string $key): bool
    {
        if (! ctype_digit($key)) {
            return false;
        }

        $id = (int) $key;

        return match ($subject) {
            FeedMuteSubject::User => $id !== $viewer->id && User::query()->whereKey($id)->exists(),
            FeedMuteSubject::Organization => Organization::query()->whereKey($id)->exists()
                && $this->organizations->isRelated($id, $viewer->id),
        };
    }

    private function muteDeniedMessage(User $viewer, FeedMuteSubject $subject, string $key): string
    {
        if ($subject === FeedMuteSubject::User && ctype_digit($key) && (int) $key === $viewer->id) {
            return 'Nie możesz wyciszyć siebie.';
        }

        if ($subject === FeedMuteSubject::Organization) {
            return 'Wyciszenie organizacji jest dostępne dla składu.';
        }

        return 'Nie można wyciszyć tego użytkownika.';
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
