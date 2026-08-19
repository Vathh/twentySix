<?php

namespace App\Rules;

use App\Models\League\League;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniquePlayerNameInLeague implements ValidationRule
{
    public function __construct(private int $leagueId)
    {
    }

    /**
     * @param  \Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $league = League::query()
            ->with(['relatedUsers.player', 'guests'])
            ->findOrFail($this->leagueId);

        $exists = collect()
            ->merge($league->relatedUsers->map(fn ($user) => $user->player)->filter())
            ->merge($league->guests)
            ->unique('id')
            ->contains('name', $value);

        if ($exists) {
            $fail('Gracz o tej nazwie już istnieje w tej lidze. Wybierz inną nazwę.');
        }
    }
}
