<?php

namespace App\Models\PointScheme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointScheme extends Model
{
    protected $fillable = [
        'name',
        'min_players',
        'max_players',
    ];

    /** @return HasMany<PointSchemeRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(PointSchemeRule::class);
    }
}
