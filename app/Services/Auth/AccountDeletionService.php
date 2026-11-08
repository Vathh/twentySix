<?php

namespace App\Services\Auth;

use App\Domain\Auth\AccountDeletionRules;
use App\Domain\Auth\SoleOrganizationAdmin;
use App\Models\Users\User;
use App\Repositories\Activity\ActivityCommentRepository;
use App\Repositories\Organization\OrganizationRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public function __construct(
        private UserRepository $users,
        private PlayerRepository $players,
        private OrganizationRepository $organizations,
        private ActivityCommentRepository $comments,
    ) {}

    public function deleteAuthenticated(User $user, string $password): void
    {
        $this->assertPassword($user, $password, 'Hasło jest nieprawidłowe.');
        $this->delete($user);
    }

    public function deleteUsingCredentials(string $email, string $password): int
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            $this->rejectCredentials();
        }

        $this->assertPassword($user, $password, 'Nieprawidłowy e-mail lub hasło.');

        return $this->delete($user);
    }

    private function delete(User $user): int
    {
        $user->loadMissing('player');
        $names = $this->organizations->soleAdminOrganizationNames((int) $user->id);
        if ($names !== []) {
            throw new SoleOrganizationAdmin($names);
        }

        $userId = (int) $user->id;
        $avatarPath = $user->player?->avatar_path;

        DB::transaction(function () use ($user): void {
            $this->comments->deleteOwnedAndKeepOthersReplies((int) $user->id);
            if ($user->player !== null) {
                $this->players->releaseAccount($user->player, AccountDeletionRules::ANONYMOUS_PLAYER_NAME);
            }
            $this->users->delete($user);
        });

        if (is_string($avatarPath) && $avatarPath !== '') {
            Storage::disk('public')->delete($avatarPath);
        }

        return $userId;
    }

    private function assertPassword(User $user, string $password, string $message): void
    {
        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => $message,
            ]);
        }
    }

    private function rejectCredentials(): never
    {
        throw ValidationException::withMessages([
            'password' => 'Nieprawidłowy e-mail lub hasło.',
        ]);
    }
}
