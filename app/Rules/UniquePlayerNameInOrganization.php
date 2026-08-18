<?php

namespace App\Rules;

use App\Models\Organization\Organization;
use App\Models\Player\Player;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Sprawdza czy nazwa gracza (gościa) jest unikalna w kontekście organizacji
 * Nie może być dwóch graczy (zarejestrowany + gość) o tej samej nazwie w organizacji
 */
class UniquePlayerNameInOrganization implements ValidationRule
{
    private int $organizationId;

    public function __construct(int $organizationId)
    {
        $this->organizationId = $organizationId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Pobierz organizację z relacjami
        $organization = Organization::with(['relatedUsers.player', 'guests'])
            ->findOrFail($this->organizationId);

        // Zbierz wszystkich graczy związanych z organizacją
        $allPlayers = collect()
            // Zarejestrowani gracze z organizacji
            ->merge($organization->relatedUsers->map(fn($user) => $user->player)->filter())
            // Goście z organizacji
            ->merge($organization->guests)
            ->unique('id');

        // Sprawdź czy istnieje gracz o tej nazwie
        $exists = $allPlayers->contains('name', $value);

        if ($exists) {
            $fail('Gracz o tej nazwie już istnieje w tej organizacji. Wybierz inną nazwę.');
        }
    }
}

