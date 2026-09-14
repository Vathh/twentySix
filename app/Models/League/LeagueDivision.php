<?php

namespace App\Models\League;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueDivision extends Model
{
    protected $fillable = [
        'league_id',
        'position',
        'name',
        'capacity',
        'starting_score',
        'legs_to_win_set',
        'sets_to_win_match',
        'game_type',
        'promote_direct',
        'promote_playoff',
    ];

    /** @return BelongsTo<League, $this> */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /** @return HasMany<LeagueDivisionMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(LeagueDivisionMember::class);
    }

    /** @return BelongsToMany<Player, $this> */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'league_division_members');
    }
}
