<?php

namespace App\Models\Player;

use App\Models\Achievements\Achievement;
use App\Models\League\League;
use App\Models\Season\Season;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    protected $fillable = ['name', 'description', 'user_id', 'league_id', 'season_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
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


