<?php

namespace App\Services\Auth;

use App\Domain\Auth\PasswordPolicyDomain;
use App\Models\Users\User;
use App\Repositories\User\UserRepository;
use App\Rules\UniquePlayerNameForRegistered;
use App\Services\Player\PlayerService;
use Illuminate\Support\Facades\Validator;

class UserRegistrationService
{
    public function __construct(
        private PlayerService $playerService,
        private UserRepository $userRepository,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, password_confirmation?: string, acceptedTerms?: mixed}  $data
     */
    public function register(array $data, bool $requirePasswordConfirmation = false): User
    {
        $rules = [
            'name' => ['required', 'string', 'max:20', new UniquePlayerNameForRegistered],
            'email' => 'required|email|max:255|unique:users',
            'password' => PasswordPolicyDomain::rules($requirePasswordConfirmation),
            'acceptedTerms' => ['accepted'],
        ];

        $validated = Validator::make($data, $rules, [
            'acceptedTerms.accepted' => 'Zaakceptuj regulamin i politykę prywatności.',
        ])->validate();

        $user = $this->userRepository->create($validated['email'], $validated['password'], now());

        $this->playerService->create($validated['name'], $user->id);
        $user->sendEmailVerificationNotification();

        return $user;
    }
}
