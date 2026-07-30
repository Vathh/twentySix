<?php

namespace App\Services\Auth;

use App\Models\Users\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PasswordChangeService
{
    /**
     * @param  array{current_password: string, password: string, password_confirmation?: string}  $data
     *
     * @throws ValidationException
     */
    public function change(User $user, array $data): void
    {
        $validated = Validator::make($data, [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('validation.current_password'),
            ]);
        }

        $user->update([
            'password' => $validated['password'],
        ]);
    }
}
