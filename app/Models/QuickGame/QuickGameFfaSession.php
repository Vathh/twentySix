<?php

namespace App\Models\QuickGame;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuickGameFfaSession extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_FINISHED = 'finished';

    protected $fillable = [
        'lobby_id',
        'legs_to_win',
        'game_type',
        'scoring_mode',
        'starting_score',
        'status',
        'player_order',
        'leg_opener_index',
        'current_player_index',
        'current_leg_number',
        'state_version',
        'quick_game_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'player_order' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function lobby(): BelongsTo
    {
        return $this->belongsTo(QuickGameLobby::class, 'lobby_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(QuickGameFfaVisit::class, 'ffa_session_id');
    }

    public function quickGame(): BelongsTo
    {
        return $this->belongsTo(QuickGame::class, 'quick_game_id');
    }

    public function playerCount(): int
    {
        return count($this->player_order ?? []);
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }
}
