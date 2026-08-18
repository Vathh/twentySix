<?php

namespace App\Models\League;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueSeasonDivision extends Model
{
    protected $fillable = [
        'league_season_id',
        'league_division_id',
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

    public function season(): BelongsTo
    {
        return $this->belongsTo(LeagueSeason::class, 'league_season_id');
    }

    public function sourceDivision(): BelongsTo
    {
        return $this->belongsTo(LeagueDivision::class, 'league_division_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LeagueSeasonParticipant::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(LeagueGame::class, 'league_season_division_id');
    }
}
