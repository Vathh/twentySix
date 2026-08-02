<?php

namespace App\Services\Auth;

use App\Domain\Auth\PasswordPolicyDomain;
use App\Models\Users\User;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PasswordChangeService
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    /**
     * @param  array{current_password: string, password: string, password_confirmation?: string}  $data
     *
     * @throws ValidationException
     */
    public function change(User $user, array $data): void
    {
        $validated = Validator::make($data, [
            'current_password' => ['required', 'string'],
            'password' => PasswordPolicyDomain::rules(),
        ])->validate();

        PasswordPolicyDomain::assertCurrentPasswordMatches(
            Hash::check($validated['current_password'], $user->password),
        );

        $this->userRepository->updatePassword($user, $validated['password']);
    }
}
