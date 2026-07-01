<?php

namespace App\Services\Auth;

use App\Models\Users\User;
use App\Rules\UniquePlayerNameForRegistered;
use App\Services\Player\PlayerService;
use Illuminate\Support\Facades\Validator;

class UserRegistrationService
{
    public function __construct(private PlayerService $playerService)
    {
    }

    /**
     * @param  array{name: string, email: string, password: string, password_confirmation?: string}  $data
     */
    public function register(array $data, bool $requirePasswordConfirmation = false): User
    {
        $rules = [
            'name' => ['required', 'string', 'max:20', new UniquePlayerNameForRegistered()],
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ];

        if ($requirePasswordConfirmation) {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $validated = Validator::make($data, $rules)->validate();

        $user = User::create([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $this->playerService->create($validated['name'], $user->id);
        $user->sendEmailVerificationNotification();

        return $user;
    }
}
