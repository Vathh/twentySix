<?php

namespace App\Services\Platform;

use App\Models\Users\User;
use App\Repositories\Platform\PlatformAdminRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformAdminService
{
    public function __construct(
        private PlatformAdminRepository $platformAdminRepository,
        private UserRepository $userRepository,
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

    public function setCanCreateLeagues(int $userId, bool $enabled): User
    {
        $user = $this->userRepository->findModel($userId);
        $this->platformAdminRepository->setCanCreateLeagues($user, $enabled);

        return $user->fresh(['player']) ?? $user;
    }
}
