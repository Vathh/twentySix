<?php

namespace App\Repositories\Activity;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\FeedMuteSubject;
use App\Domain\Activity\ProfileFeedVisibility;
use App\Models\Activity\FeedChannelSetting;
use App\Models\Activity\FeedMute;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Users\User;
use Illuminate\Support\Collection;

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

    public function isMuted(int $userId, FeedMuteSubject $subject, string $key): bool
    {
        return FeedMute::query()
            ->where('user_id', $userId)
            ->where('subject_type', $subject->value)
            ->where('subject_key', $key)
            ->exists();
    }

    public function mute(int $userId, FeedMuteSubject $subject, string $key): void
    {
        FeedMute::query()->firstOrCreate([
            'user_id' => $userId,
            'subject_type' => $subject->value,
            'subject_key' => $key,
        ]);
    }

    public function unmute(int $userId, FeedMuteSubject $subject, string $key): void
    {
        FeedMute::query()
            ->where('user_id', $userId)
            ->where('subject_type', $subject->value)
            ->where('subject_key', $key)
            ->delete();
    }

    /**
     * @return list<int>
     */
    public function mutedIds(int $userId, FeedMuteSubject $subject): array
    {
        return FeedMute::query()
            ->where('user_id', $userId)
            ->where('subject_type', $subject->value)
            ->pluck('subject_key')
            ->map(fn ($key) => (int) $key)
            ->all();
    }

    /**
     * @return list<array{subjectType: string, subjectKey: string, label: string}>
     */
    public function mutedList(int $userId): array
    {
        /** @var Collection<int, FeedMute> $rows */
        $rows = FeedMute::query()->where('user_id', $userId)->orderBy('id')->get();
        $organizationIds = [];
        $userIds = [];

        foreach ($rows as $row) {
            if ($row->subject_type === FeedMuteSubject::Organization->value) {
                $organizationIds[] = (int) $row->subject_key;
            }
            if ($row->subject_type === FeedMuteSubject::User->value) {
                $userIds[] = (int) $row->subject_key;
            }
        }

        $organizationNames = $organizationIds === []
            ? []
            : Organization::query()->whereIn('id', $organizationIds)->pluck('name', 'id')->all();
        $playerRows = $userIds === []
            ? collect()
            : Player::query()->whereIn('user_id', $userIds)->get(['id', 'user_id', 'name']);
        $playerNames = $playerRows->pluck('name', 'user_id')->all();
        $playerIdByUser = $playerRows->pluck('id', 'user_id')->all();

        return $rows->map(function (FeedMute $row) use ($organizationNames, $playerNames, $playerIdByUser) {
            $key = (int) $row->subject_key;
            $label = match ($row->subject_type) {
                FeedMuteSubject::Organization->value => (string) ($organizationNames[$key] ?? 'Organizacja'),
                FeedMuteSubject::User->value => (string) ($playerNames[$key] ?? 'Gracz'),
                default => $row->subject_key,
            };

            return [
                'subjectType' => $row->subject_type,
                'subjectKey' => $row->subject_key,
                'label' => $label,
                'playerId' => $row->subject_type === FeedMuteSubject::User->value
                    ? ($playerIdByUser[$key] ?? null)
                    : null,
            ];
        })->all();
    }
}
