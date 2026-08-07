<?php

namespace App\Models\QuickGame;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickGameLobbyRematchIntent extends Model
{
    protected $fillable = [
        'source_lobby_id',
        'player_id',
    ];

    public function sourceLobby(): BelongsTo
    {
        return $this->belongsTo(QuickGameLobby::class, 'source_lobby_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }
}
