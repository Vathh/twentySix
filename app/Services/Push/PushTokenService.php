<?php

namespace App\Services\Push;

use App\Repositories\Push\UserPushTokenRepository;

class PushTokenService
{
    public function __construct(
        private UserPushTokenRepository $tokenRepository,
    ) {
    }

    public function upsert(
        int $userId,
        string $expoPushToken,
        string $platform = 'unknown',
        ?string $deviceName = null,
    ): void {
        $this->tokenRepository->upsert(
            userId: $userId,
            expoPushToken: $expoPushToken,
            platform: $platform,
            deviceName: $deviceName,
        );
    }

    public function deleteForUser(int $userId, string $expoPushToken): void
    {
        $this->tokenRepository->deleteByToken(
            expoPushToken: $expoPushToken,
            userId: $userId,
        );
    }
}
