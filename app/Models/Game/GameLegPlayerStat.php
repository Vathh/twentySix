<?php

namespace App\Models\Game;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameLegPlayerStat extends Model
{
    protected $fillable = [
        'game_leg_id',
        'player_id',
        'leg_average',
        'first_nine_average',
        'highest_visit',
        'highest_finish',
        'darts_thrown',
        'checkout_dart',
        'double_tracked',
        'double_attempts',
        'double_successes',
    ];

    protected $casts = [
        'leg_average' => 'float',
        'first_nine_average' => 'float',
        'double_tracked' => 'boolean',
    ];

    /** @return BelongsTo<GameLeg, $this> */
    public function gameLeg(): BelongsTo
    {
        return $this->belongsTo(GameLeg::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
