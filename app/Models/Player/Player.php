<?php

namespace App\Models\Player;

use App\Models\Achievements\Achievement;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = ['name', 'description', 'user_id', 'organization_id', 'season_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    public function playerStat(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlayerStat::class);
    }
}


