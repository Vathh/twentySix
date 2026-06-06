<?php

namespace App\Models\Tournament;

use App\Enums\TournamentStatus;
use App\Models\Achievements\Achievement;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\PointScheme\PointScheme;
use App\Models\Season\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'season_id',
        'date',
        'status',
        'point_scheme_id',
        'groups_count',
        'advance_per_group',
        'tablets_count',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => TournamentStatus::class
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function playoffGames(): HasMany
    {
        return $this->hasMany(PlayoffGame::class);
    }

    public function groupStandings(): HasMany
    {
        return $this->hasMany(GroupStanding::class);
    }

    public function pointScheme(): BelongsTo
    {
        return $this->belongsTo(PointScheme::class, 'point_scheme_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TournamentResult::class);
    }
}


