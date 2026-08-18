<?php

namespace App\Rules;

use App\Models\Player\Player;
use App\Models\Season\Season;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Sprawdza czy nazwa gracza (gościa) jest unikalna w kontekście sezonu i organizacji
 * Nie może być dwóch graczy (zarejestrowany + gość) o tej samej nazwie w sezonie/organizacji
 */
class UniquePlayerNameInSeason implements ValidationRule
{
    private int $seasonId;
    private int $organizationId;

    public function __construct(int $seasonId, int $organizationId)
    {
        $this->seasonId = $seasonId;
        $this->organizationId = $organizationId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Pobierz sezon z relacjami
        $season = Season::with(['relatedUsers.player', 'guests', 'organization.relatedUsers.player', 'organization.guests'])
            ->findOrFail($this->seasonId);

        // Zbierz wszystkich graczy związanych z sezonem i organizacją
        $allPlayers = collect()
            // Zarejestrowani gracze z sezonu
            ->merge($season->relatedUsers->map(fn($user) => $user->player)->filter())
            // Goście z sezonu
            ->merge($season->guests)
            // Zarejestrowani gracze z organizacji
            ->merge($season->organization->relatedUsers->map(fn($user) => $user->player)->filter())
            // Goście z organizacji
            ->merge($season->organization->guests)
            ->unique('id');

        // Sprawdź czy istnieje gracz o tej nazwie
        $exists = $allPlayers->contains('name', $value);

        if ($exists) {
            $fail('Gracz o tej nazwie już istnieje w tym sezonie lub organizacji. Wybierz inną nazwę.');
        }
    }
}

