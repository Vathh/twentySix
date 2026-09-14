<?php

namespace App\Models\Career;

use App\Enums\CareerSource;
use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlayerGameSnapshot extends Model
{
    protected $fillable = [
        'player_id',
        'source',
        'game_type',
        'occurred_at',
        'client_uuid',
        'sourceable_type',
        'sourceable_id',
        'metrics',
    ];

    protected $casts = [
        'source' => CareerSource::class,
        'occurred_at' => 'datetime',
        'metrics' => 'array',
    ];

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return MorphTo<Model, $this> */
    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
