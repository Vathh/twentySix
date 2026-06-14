<?php

namespace App\Models\QuickGame;

use App\Models\Users\User;
use App\Models\QuickGame\QuickGame;
use App\Models\QuickGame\QuickGameFfaSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuickGameLobby extends Model
{
    protected $fillable = [
        'host_id',
        'status',
        'legs_count',
        'game_type',
        'scoring_mode',
        'quick_game_id',
        'ffa_session_id',
        'player_order',
        'started_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'player_order' => 'array',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(QuickGameLobbyPlayer::class, 'lobby_id')->orderBy('created_at');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(QuickGameLobbyInvitation::class, 'lobby_id');
    }

    public function quickGame(): BelongsTo
    {
        return $this->belongsTo(QuickGame::class, 'quick_game_id');
    }

    public function ffaSession(): BelongsTo
    {
        return $this->belongsTo(QuickGameFfaSession::class, 'ffa_session_id');
    }
}


