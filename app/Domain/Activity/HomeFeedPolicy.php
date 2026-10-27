<?php

namespace App\Domain\Activity;

/**
 * Historia jest widoczna, gdy otwarty jest choć jeden jej kanał.
 * Wyciszenie zamyka tylko kanał, którego dotyczy.
 */
final class HomeFeedPolicy
{
    /**
     * @param  list<ActivityChannel>  $channels
     * @param  array<int, true>  $friendIds
     * @param  array<int, true>  $organizationIds
     * @param  array<int, true>  $mutedUserIds
     * @param  array<int, true>  $mutedOrganizationIds
     */
    public function visible(
        int $viewerId,
        ?int $actorUserId,
        ?int $organizationId,
        array $channels,
        bool $friendsChannelEnabled,
        bool $organizationChannelEnabled,
        array $friendIds,
        array $organizationIds,
        array $mutedUserIds,
        array $mutedOrganizationIds,
    ): bool {
        foreach ($channels as $channel) {
            $open = match ($channel) {
                ActivityChannel::Friends => $this->friendsOpen(
                    $viewerId,
                    $actorUserId,
                    $friendsChannelEnabled,
                    $friendIds,
                    $mutedUserIds,
                ),
                ActivityChannel::Organization => $this->organizationOpen(
                    $organizationId,
                    $organizationChannelEnabled,
                    $organizationIds,
                    $mutedOrganizationIds,
                ),
            };

            if ($open) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, true>  $friendIds
     * @param  array<int, true>  $mutedUserIds
     */
    private function friendsOpen(
        int $viewerId,
        ?int $actorUserId,
        bool $channelEnabled,
        array $friendIds,
        array $mutedUserIds,
    ): bool {
        if ($actorUserId === null) {
            return false;
        }

        if ($actorUserId === $viewerId) {
            return true;
        }

        if (! $channelEnabled || isset($mutedUserIds[$actorUserId])) {
            return false;
        }

        return isset($friendIds[$actorUserId]);
    }

    /**
     * @param  array<int, true>  $organizationIds
     * @param  array<int, true>  $mutedOrganizationIds
     */
    private function organizationOpen(
        ?int $organizationId,
        bool $channelEnabled,
        array $organizationIds,
        array $mutedOrganizationIds,
    ): bool {
        if ($organizationId === null || ! $channelEnabled) {
            return false;
        }

        if (isset($mutedOrganizationIds[$organizationId])) {
            return false;
        }

        return isset($organizationIds[$organizationId]);
    }
}
