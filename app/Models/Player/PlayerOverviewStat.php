<?php

namespace App\Models\Player;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerOverviewStat extends Model
{
    protected $table = 'player_overview_stats';

    protected $fillable = [
        'player_id',
        'games_total',
        'wins_total',
        'games_tournament',
        'wins_tournament',
        'games_league',
        'wins_league',
        'games_quick',
        'wins_quick',
        'tournament_place_1',
        'tournament_place_2',
        'tournament_place_3',
        'league_titles',
        'unique_opponents',
        'top_opponents',
        'activity_days',
        'current_streak',
        'longest_streak',
        'last_activity_on',
    ];

    protected $casts = [
        'last_activity_on' => 'date',
        'top_opponents' => 'array',
    ];

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
