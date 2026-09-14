<?php

namespace App\Models\QuickGame;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuickGameLobby extends Model
{
    protected $fillable = [
        'host_id',
        'status',
        'starting_score',
        'legs_to_win_set',
        'sets_to_win_match',
        'game_type',
        'bob27_mode',
        'bob27_bull',
        'scoring_mode',
        'quick_game_id',
        'rematch_lobby_id',
        'ffa_session_id',
        'player_order',
        'started_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'player_order' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /** @return HasMany<QuickGameLobbyPlayer, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(QuickGameLobbyPlayer::class, 'lobby_id')->orderBy('created_at');
    }

    /** @return HasMany<QuickGameLobbyInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(QuickGameLobbyInvitation::class, 'lobby_id');
    }

    /** @return HasMany<QuickGameLobbyRematchIntent, $this> */
    public function rematchIntents(): HasMany
    {
        return $this->hasMany(QuickGameLobbyRematchIntent::class, 'source_lobby_id');
    }

    /** @return BelongsTo<QuickGameLobby, $this> */
    public function rematchLobby(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rematch_lobby_id');
    }

    /** @return BelongsTo<QuickGame, $this> */
    public function quickGame(): BelongsTo
    {
        return $this->belongsTo(QuickGame::class, 'quick_game_id');
    }

    /** @return BelongsTo<QuickGameFfaSession, $this> */
    public function ffaSession(): BelongsTo
    {
        return $this->belongsTo(QuickGameFfaSession::class, 'ffa_session_id');
    }
}
