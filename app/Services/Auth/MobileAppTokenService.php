<?php

namespace App\Services\Auth;

use App\Models\Users\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class MobileAppTokenService
{
    public function tokenName(): string
    {
        return (string) config('mobile.token_name', 'mobile-app');
    }

    public function ttlDays(): int
    {
        return max(1, (int) config('mobile.token_ttl_days', 30));
    }

    public function expiresAt(): Carbon
    {
        return now()->addDays($this->ttlDays());
    }

    /**
     * @return array{token: string, user: array{id: int, email: string, name: string|null, playerId: int|null}}
     */
    public function issueForUser(User $user): array
    {
        $user->loadMissing('player');

        $accessToken = $user->createToken(
            $this->tokenName(),
            ['*'],
            $this->expiresAt(),
        );

        return [
            'token' => $accessToken->plainTextToken,
            'user' => $this->userPayload($user),
        ];
    }

    /**
     * Rotacja tokena — stary unieważniony, nowy ważny kolejne TTL dni.
     *
     * @return array{token: string, user: array{id: int, email: string, name: string|null, playerId: int|null}}
     */
    public function refresh(User $user, PersonalAccessToken $currentToken): array
    {
        if ($currentToken->name !== $this->tokenName()) {
            throw new \InvalidArgumentException('Token nie obsługuje odświeżenia sesji.');
        }

        $currentToken->delete();

        return $this->issueForUser($user);
    }

    public function revokeCurrent(User $user): void
    {
        $token = $user->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }
    }

    /**
     * @return array{id: int, email: string, name: string|null, playerId: int|null}
     */
    public function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->player->name ?? null,
            'playerId' => $user->player?->id,
        ];
    }
}
