<?php

namespace App\Models\PointScheme;

use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointScheme extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'min_players',
        'max_players',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<PointSchemeRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(PointSchemeRule::class);
    }
}
