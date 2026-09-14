<?php

namespace App\Models\Badge;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerBadgeEvent extends Model
{
    protected $fillable = [
        'player_id',
        'category',
        'badge_key',
        'amount',
        'source_kind',
        'source_id',
    ];

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
