<?php

namespace App\Models\QuickGame;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickGameResult extends Model
{
    protected $fillable = [
        'quick_game_id',
        'player_id',
        'score',
        'place',
        'average',
        'darts_thrown',
        'points_earned',
    ];

    protected $casts = [
        'score' => 'integer',
        'place' => 'integer',
        'average' => 'decimal:2',
        'darts_thrown' => 'integer',
        'points_earned' => 'integer',
    ];

    /** @return BelongsTo<QuickGame, $this> */
    public function quickGame(): BelongsTo
    {
        return $this->belongsTo(QuickGame::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
