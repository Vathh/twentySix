<?php

namespace App\Rules;

use App\Models\Player\Player;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniquePlayerInSeasonAndOrganization implements ValidationRule
{
    private int $seasonId;
    private int $organizationId;

    /**
     * @param int $seasonId
     * @param int $organizationId
     */
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
        $exists = Player::where('name', $value)
            ->where(function($query) {
                $query->where('season_id', $this->seasonId)
                    ->orWhere('organization_id', $this->organizationId);
            })
            ->exists();

        if ($exists) {
            $fail('Gracz o tej nazwie już istnieje.');
        }
    }
}

