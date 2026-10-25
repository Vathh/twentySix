<?php

namespace App\Services\Auth;

use App\Domain\Auth\PasswordPolicyDomain;
use App\Domain\Auth\PasswordResetDomain;
use App\Models\Users\User;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function __construct(
        private UserRepository $userRepository,
        private MobileAppTokenService $mobileAppTokenService,
    ) {}

    public function requestLink(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null || $user->isBanned()) {
            return;
        }

        Password::broker('users')->sendResetLink(['email' => $user->email]);
    }

    /**
     * @throws ValidationException
     */
    public function reset(string $email, string $token, string $password, string $passwordConfirmation): void
    {
        Validator::make(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'email' => ['required', 'email'],
                'token' => ['required', 'string'],
                'password' => PasswordPolicyDomain::rules(),
            ],
        )->validate();

        $status = Password::broker('users')->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            function (User $user, string $plainPassword): void {
                $this->userRepository->updatePassword($user, $plainPassword);
                $this->userRepository->rotateRememberToken($user);
                $this->mobileAppTokenService->revokeAll($user);
                if ($user->email_verified_at === null) {
                    $this->userRepository->markEmailVerified($user);
                }
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => PasswordResetDomain::INVALID_LINK_MESSAGE,
            ]);
        }
    }
}
