<?php

namespace App\Rules;

use App\Models\Player;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Sprawdza czy nazwa gracza zarejestrowanego jest unikalna globalnie
 * (zarejestrowani gracze muszą mieć unikalne nazwy)
 */
class UniquePlayerNameForRegistered implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Player::where('name', $value)
            ->whereNotNull('user_id') // Tylko zarejestrowani gracze
            ->exists();

        if ($exists) {
            $fail('Gracz o tej nazwie już istnieje. Wybierz inną nazwę.');
        }
    }
}
