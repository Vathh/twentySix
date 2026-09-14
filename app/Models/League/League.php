<?php

namespace App\Models\League;

use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    protected $fillable = ['organization_id', 'name', 'description'];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<LeagueDivision, $this> */
    public function divisions(): HasMany
    {
        return $this->hasMany(LeagueDivision::class)->orderBy('position');
    }

    /** @return HasMany<LeagueDivisionMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(LeagueDivisionMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function relatedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_user');
    }

    /** @return HasMany<Player, $this> */
    public function guests(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /** @return HasMany<LeagueSeason, $this> */
    public function seasons(): HasMany
    {
        return $this->hasMany(LeagueSeason::class)->orderByDesc('id');
    }

    /** @return HasMany<LeagueInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(LeagueInvitation::class);
    }
}
