<?php

namespace App\Models\QuickGame;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickGameLobbyPlayer extends Model
{
    protected $fillable = [
        'lobby_id',
        'player_id',
        'temp_player_name',
        'is_registered',
        'is_ready',
    ];

    protected $casts = [
        'is_registered' => 'boolean',
        'is_ready' => 'boolean',
    ];

    /** @return BelongsTo<QuickGameLobby, $this> */
    public function lobby(): BelongsTo
    {
        return $this->belongsTo(QuickGameLobby::class, 'lobby_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
