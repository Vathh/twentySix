<?php

namespace App\Services\Moderation;

use App\Models\Moderation\UserBlock;
use App\Models\Users\User;
use App\Repositories\Moderation\UserBlockRepository;
use App\Repositories\User\UserRepository;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserBlockService
{
    public function __construct(
        private UserBlockRepository $blocks,
        private UserRepository $users,
    ) {}

    public function block(User $blocker, int $blockedId): void
    {
        if ($blockedId === (int) $blocker->id) {
            throw new DomainException('Nie możesz zablokować siebie.');
        }

        $this->requireUser($blockedId);
        $this->blocks->block((int) $blocker->id, $blockedId);
    }

    public function unblock(User $blocker, int $blockedId): void
    {
        $this->blocks->unblock((int) $blocker->id, $blockedId);
    }

    /**
     * @return list<int>
     */
    public function blockedIds(int $blockerId): array
    {
        return $this->blocks->blockedIds($blockerId);
    }

    public function isBlocked(int $blockerId, int $blockedId): bool
    {
        return $this->blocks->isBlocked($blockerId, $blockedId);
    }

    /**
     * @return list<array{userId: int, playerId: ?int, name: string}>
     */
    public function blockedPeople(User $blocker): array
    {
        return $this->blocks->listFor((int) $blocker->id)
            ->map(function (UserBlock $block) {
                $player = $block->blocked?->player;

                return [
                    'userId' => (int) $block->blocked_id,
                    'playerId' => $player === null ? null : (int) $player->id,
                    'name' => (string) ($player->name ?? 'Gracz'),
                ];
            })
            ->values()
            ->all();
    }

    private function requireUser(int $userId): void
    {
        try {
            $this->users->findModel($userId);
        } catch (ModelNotFoundException) {
            throw new DomainException('Nie ma takiego gracza.');
        }
    }
}
