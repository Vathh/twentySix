<?php

namespace App\Models\QuickGame;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickGameLobbyInvitation extends Model
{
    protected $fillable = [
        'lobby_id',
        'invited_player_id',
        'status',
    ];

    /** @return BelongsTo<QuickGameLobby, $this> */
    public function lobby(): BelongsTo
    {
        return $this->belongsTo(QuickGameLobby::class, 'lobby_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function invitedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'invited_player_id');
    }
}
