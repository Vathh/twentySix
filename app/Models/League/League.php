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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(LeagueDivision::class)->orderBy('position');
    }

    public function members(): HasMany
    {
        return $this->hasMany(LeagueDivisionMember::class);
    }

    public function relatedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_user');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(LeagueSeason::class)->orderByDesc('id');
    }
}
