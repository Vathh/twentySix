<?php

namespace App\Repositories\Push;

use App\Models\Push\UserPushToken;
use Illuminate\Support\Collection;

class UserPushTokenRepository
{
    public function upsert(
        int $userId,
        string $expoPushToken,
        string $platform = 'unknown',
        ?string $deviceName = null,
    ): UserPushToken {
        $token = UserPushToken::query()->updateOrCreate(
            ['expo_push_token' => $expoPushToken],
            [
                'user_id' => $userId,
                'platform' => $platform,
                'device_name' => $deviceName,
                'last_seen_at' => now(),
            ],
        );

        return $token;
    }

    public function deleteByToken(string $expoPushToken, ?int $userId = null): bool
    {
        $query = UserPushToken::query()->where('expo_push_token', $expoPushToken);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->delete() > 0;
    }

    public function deleteByTokens(array $expoPushTokens): int
    {
        if ($expoPushTokens === []) {
            return 0;
        }

        return UserPushToken::query()
            ->whereIn('expo_push_token', $expoPushTokens)
            ->delete();
    }

    /**
     * @return Collection<int, string>
     */
    public function getTokensForUser(int $userId): Collection
    {
        return UserPushToken::query()
            ->where('user_id', $userId)
            ->pluck('expo_push_token');
    }
}
