<?php

namespace App\Models\League;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueDivisionMember extends Model
{
    protected $fillable = ['league_id', 'league_division_id', 'player_id'];

    /** @return BelongsTo<League, $this> */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /** @return BelongsTo<LeagueDivision, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(LeagueDivision::class, 'league_division_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
