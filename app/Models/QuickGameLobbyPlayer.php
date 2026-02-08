<?php

namespace App\Models;

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

    public function lobby(): BelongsTo
    {
        return $this->belongsTo(QuickGameLobby::class, 'lobby_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
