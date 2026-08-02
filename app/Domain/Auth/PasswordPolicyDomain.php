<?php

namespace App\Domain\Auth;

use Illuminate\Validation\ValidationException;

/**
 * Polityka haseł — wspólna dla rejestracji i zmiany hasła.
 */
class PasswordPolicyDomain
{
    public const MIN_LENGTH = 8;

    /**
     * Reguły walidacji (Laravel) dla nowego hasła.
     *
     * @return list<string>
     */
    public static function rules(bool $requireConfirmation = true): array
    {
        $rules = ['required', 'string', 'min:'.self::MIN_LENGTH];

        if ($requireConfirmation) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /**
     * Waliduje wynik weryfikacji obecnego hasła (Hash::check wykonuje Service — to tylko decyzja domenowa).
     *
     * @throws ValidationException
     */
    public static function assertCurrentPasswordMatches(bool $matches): void
    {
        if (! $matches) {
            throw ValidationException::withMessages([
                'current_password' => __('validation.current_password'),
            ]);
        }
    }
}
