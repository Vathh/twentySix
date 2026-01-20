<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointScheme extends Model
{
    protected $fillable = [
        'name',
        'min_players',
        'max_players'
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(PointSchemeRule::class);
    }
}
