<?php

namespace App\Models\PointScheme;

use App\Enums\GameStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointSchemeRule extends Model
{
    protected $fillable = [
        'point_scheme_id',
        'elimination_stage',
        'place',
        'points',
    ];

    protected $casts = [
        'elimination_stage' => GameStage::class,
    ];

    /** @return BelongsTo<PointScheme, $this> */
    public function pointScheme(): BelongsTo
    {
        return $this->belongsTo(PointScheme::class);
    }
}
