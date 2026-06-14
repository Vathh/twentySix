<?php

namespace App\Models\QuickGame;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickGameFfaVisit extends Model
{
    protected $fillable = [
        'ffa_session_id',
        'leg_number',
        'player_id',
        'visit_number',
        'score',
        'remaining_before',
        'remaining_after',
        'darts_in_visit',
        'closed_leg',
        'bust',
        'is_voided',
        'client_visit_id',
    ];

    protected $casts = [
        'closed_leg' => 'boolean',
        'bust' => 'boolean',
        'is_voided' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuickGameFfaSession::class, 'ffa_session_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
