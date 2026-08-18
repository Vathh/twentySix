<?php

namespace App\Models\League;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueDivisionMember extends Model
{
    protected $fillable = ['league_id', 'league_division_id', 'player_id'];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(LeagueDivision::class, 'league_division_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
