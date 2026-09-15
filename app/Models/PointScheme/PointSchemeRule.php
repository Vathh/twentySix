<?php

namespace App\Models\PointScheme;

use App\Enums\PointSchemeFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointSchemeRule extends Model
{
    protected $fillable = [
        'point_scheme_id',
        'format',
        'place_from',
        'place_to',
        'points',
    ];

    protected $casts = [
        'format' => PointSchemeFormat::class,
    ];

    /** @return BelongsTo<PointScheme, $this> */
    public function pointScheme(): BelongsTo
    {
        return $this->belongsTo(PointScheme::class);
    }
}
