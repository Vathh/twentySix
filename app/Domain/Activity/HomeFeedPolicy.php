<?php

namespace App\Domain\Activity;

/**
 * Historia jest widoczna, gdy otwarty jest choć jeden jej kanał.
 * Brak obserwowania zamyka tylko kanał, którego dotyczy.
 */
final class HomeFeedPolicy
{
    /**
     * @param  list<ActivityChannel>  $channels
     * @param  array<int, true>  $followedUserIds
     * @param  array<int, true>  $followedOrganizationIds
     */
    public function visible(
        int $viewerId,
        ?int $actorUserId,
        ?int $organizationId,
        array $channels,
        bool $friendsChannelEnabled,
        bool $organizationChannelEnabled,
        array $followedUserIds,
        array $followedOrganizationIds,
    ): bool {
        foreach ($channels as $channel) {
            $open = match ($channel) {
                ActivityChannel::Friends => $this->friendsOpen(
                    $viewerId,
                    $actorUserId,
                    $friendsChannelEnabled,
                    $followedUserIds,
                ),
                ActivityChannel::Organization => $this->organizationOpen(
                    $organizationId,
                    $organizationChannelEnabled,
                    $followedOrganizationIds,
                ),
            };

            if ($open) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, true>  $followedUserIds
     */
    private function friendsOpen(
        int $viewerId,
        ?int $actorUserId,
        bool $channelEnabled,
        array $followedUserIds,
    ): bool {
        if ($actorUserId === null) {
            return false;
        }

        if ($actorUserId === $viewerId) {
            return true;
        }

        if (! $channelEnabled) {
            return false;
        }

        return isset($followedUserIds[$actorUserId]);
    }

    /**
     * @param  array<int, true>  $followedOrganizationIds
     */
    private function organizationOpen(
        ?int $organizationId,
        bool $channelEnabled,
        array $followedOrganizationIds,
    ): bool {
        if ($organizationId === null || ! $channelEnabled) {
            return false;
        }

        return isset($followedOrganizationIds[$organizationId]);
    }
}
