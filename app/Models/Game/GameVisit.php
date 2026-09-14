<?php

namespace App\Models\Game;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameVisit extends Model
{
    protected $fillable = [
        'game_leg_id',
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
        'darts',
    ];

    protected $casts = [
        'closed_leg' => 'boolean',
        'bust' => 'boolean',
        'is_voided' => 'boolean',
        'darts' => 'array',
    ];

    /** @return BelongsTo<GameLeg, $this> */
    public function gameLeg(): BelongsTo
    {
        return $this->belongsTo(GameLeg::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
