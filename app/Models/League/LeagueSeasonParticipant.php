<?php

namespace App\Models\League;

use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueSeasonParticipant extends Model
{
    protected $fillable = [
        'league_season_id',
        'league_season_division_id',
        'player_id',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'withdrawn_at' => 'datetime',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(LeagueSeason::class, 'league_season_id');
    }

    public function seasonDivision(): BelongsTo
    {
        return $this->belongsTo(LeagueSeasonDivision::class, 'league_season_division_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
