<?php

namespace App\Rules;

use App\Models\Player\Player;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Sprawdza czy nazwa gracza zarejestrowanego jest unikalna globalnie
 * (zarejestrowani gracze muszą mieć unikalne nazwy)
 */
class UniquePlayerNameForRegistered implements ValidationRule
{
    public function __construct(private readonly ?int $ignorePlayerId = null) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = Player::query()
            ->where('name', $value)
            ->whereNotNull('user_id');

        if ($this->ignorePlayerId !== null) {
            $query->where('id', '!=', $this->ignorePlayerId);
        }

        if ($query->exists()) {
            $fail('Ta nazwa jest już zajęta.');
        }
    }
}
