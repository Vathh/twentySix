<?php

namespace App\Models\Career;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingGame extends Model
{
    protected $fillable = [
        'player_id',
        'client_uuid',
        'game_type',
        'completed_at',
        'format',
        'metrics',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'format' => 'array',
        'metrics' => 'array',
    ];

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
