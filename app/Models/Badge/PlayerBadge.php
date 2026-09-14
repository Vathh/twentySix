<?php

namespace App\Models\Badge;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerBadge extends Model
{
    protected $fillable = [
        'player_id',
        'category',
        'badge_key',
        'times_earned',
        'first_unlocked_at',
        'last_earned_at',
        'source_quality',
    ];

    protected function casts(): array
    {
        return [
            'first_unlocked_at' => 'datetime',
            'last_earned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
