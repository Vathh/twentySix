<?php

namespace App\Repositories\Activity;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\ProfileFeedVisibility;
use App\Models\Activity\FeedChannelSetting;
use App\Models\Users\User;

class FeedPreferenceRepository
{
    public function visibility(User $user): ProfileFeedVisibility
    {
        $value = $user->profile_feed_visibility;

        if ($value instanceof ProfileFeedVisibility) {
            return $value;
        }

        return ProfileFeedVisibility::tryFrom((string) $value) ?? ProfileFeedVisibility::Public;
    }

    public function setVisibility(User $user, ProfileFeedVisibility $visibility): void
    {
        $user->profile_feed_visibility = $visibility;
        $user->save();
    }

    public function channelEnabled(int $userId, ActivityChannel $channel): bool
    {
        $enabled = FeedChannelSetting::query()
            ->where('user_id', $userId)
            ->where('channel', $channel->value)
            ->value('enabled');

        return $enabled === null ? true : filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    }

    public function setChannel(int $userId, ActivityChannel $channel, bool $enabled): void
    {
        FeedChannelSetting::query()->updateOrCreate(
            ['user_id' => $userId, 'channel' => $channel->value],
            ['enabled' => $enabled],
        );
    }
}
