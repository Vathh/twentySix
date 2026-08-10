<?php

namespace App\Services\Platform;

use App\Models\Users\User;
use App\Repositories\Platform\PlatformAdminRepository;
use App\Repositories\Player\PlayerGameHistoryRepository;
use App\Repositories\User\UserRepository;
use App\Services\Auth\MobileAppTokenService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformAdminService
{
    public function __construct(
        private PlatformAdminRepository $platformAdminRepository,
        private UserRepository $userRepository,
        private MobileAppTokenService $mobileAppTokenService,
        private PlayerGameHistoryRepository $playerGameHistoryRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return $this->platformAdminRepository->dashboardStats();
    }

    public function listUsers(?string $search = null): LengthAwarePaginator
    {
        return $this->platformAdminRepository->paginateUsers($search);
    }

    /**
     * @return array{user: User, activity: array<string, mixed>, recentGames: array{items: array<int, array<string, mixed>>, has_more: bool}}
     */
    public function userDetail(int $userId): array
    {
        $user = $this->userRepository->findModel($userId);
        $user->loadMissing('player');

        $playerId = $user->player?->id;
        $recentGames = $playerId !== null
            ? $this->playerGameHistoryRepository->getHistoryPage($playerId, 1)
            : ['items' => [], 'has_more' => false];

        return [
            'user' => $user,
            'activity' => $this->platformAdminRepository->userActivity($user),
            'recentGames' => $recentGames,
        ];
    }

    public function setCanCreateLeagues(int $userId, bool $enabled): User
    {
        $user = $this->userRepository->findModel($userId);
        $this->platformAdminRepository->setCanCreateLeagues($user, $enabled);

        return $user->fresh(['player']) ?? $user;
    }

    public function setBanned(int $userId, bool $banned): User
    {
        $user = $this->userRepository->findModel($userId);

        if ($user->isPlatformAdmin()) {
            throw new \InvalidArgumentException('Nie można zablokować konta platform admina.');
        }

        $this->platformAdminRepository->setBanned($user, $banned);

        if ($banned) {
            $this->mobileAppTokenService->revokeAll($user);
        }

        return $user->fresh(['player']) ?? $user;
    }
}
