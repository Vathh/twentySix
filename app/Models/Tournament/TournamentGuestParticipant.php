<?php

namespace App\Models\Tournament;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentGuestParticipant extends Model
{
    protected $fillable = [
        'tournament_id',
        'player_id',
    ];

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
