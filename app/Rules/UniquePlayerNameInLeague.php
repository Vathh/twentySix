<?php

namespace App\Rules;

use App\Models\League;
use App\Models\Player;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Sprawdza czy nazwa gracza (gościa) jest unikalna w kontekście ligi
 * Nie może być dwóch graczy (zarejestrowany + gość) o tej samej nazwie w lidze
 */
class UniquePlayerNameInLeague implements ValidationRule
{
    private int $leagueId;

    public function __construct(int $leagueId)
    {
        $this->leagueId = $leagueId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Pobierz ligę z relacjami
        $league = League::with(['relatedUsers.player', 'guests'])
            ->findOrFail($this->leagueId);

        // Zbierz wszystkich graczy związanych z ligą
        $allPlayers = collect()
            // Zarejestrowani gracze z ligi
            ->merge($league->relatedUsers->map(fn($user) => $user->player)->filter())
            // Goście z ligi
            ->merge($league->guests)
            ->unique('id');

        // Sprawdź czy istnieje gracz o tej nazwie
        $exists = $allPlayers->contains('name', $value);

        if ($exists) {
            $fail('Gracz o tej nazwie już istnieje w tej lidze. Wybierz inną nazwę.');
        }
    }
}
