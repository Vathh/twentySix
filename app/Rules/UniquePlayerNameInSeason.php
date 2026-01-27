<?php

namespace App\Rules;

use App\Models\Player;
use App\Models\Season;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Sprawdza czy nazwa gracza (gościa) jest unikalna w kontekście sezonu i ligi
 * Nie może być dwóch graczy (zarejestrowany + gość) o tej samej nazwie w sezonie/ligi
 */
class UniquePlayerNameInSeason implements ValidationRule
{
    private int $seasonId;
    private int $leagueId;

    public function __construct(int $seasonId, int $leagueId)
    {
        $this->seasonId = $seasonId;
        $this->leagueId = $leagueId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Pobierz sezon z relacjami
        $season = Season::with(['relatedUsers.player', 'guests', 'league.relatedUsers.player', 'league.guests'])
            ->findOrFail($this->seasonId);

        // Zbierz wszystkich graczy związanych z sezonem i ligą
        $allPlayers = collect()
            // Zarejestrowani gracze z sezonu
            ->merge($season->relatedUsers->map(fn($user) => $user->player)->filter())
            // Goście z sezonu
            ->merge($season->guests)
            // Zarejestrowani gracze z ligi
            ->merge($season->league->relatedUsers->map(fn($user) => $user->player)->filter())
            // Goście z ligi
            ->merge($season->league->guests)
            ->unique('id');

        // Sprawdź czy istnieje gracz o tej nazwie
        $exists = $allPlayers->contains('name', $value);

        if ($exists) {
            $fail('Gracz o tej nazwie już istnieje w tym sezonie lub lidze. Wybierz inną nazwę.');
        }
    }
}
