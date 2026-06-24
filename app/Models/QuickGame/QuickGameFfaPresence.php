<?php

namespace App\Models\QuickGame;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickGameFfaPresence extends Model
{
    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_LEFT = 'left';

    protected $table = 'quick_game_ffa_presence';

    protected $fillable = [
        'ffa_session_id',
        'player_id',
        'status',
        'last_seen_at',
        'left_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuickGameFfaSession::class, 'ffa_session_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }
}
