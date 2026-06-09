<?php

namespace App\Models\Player;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStat extends Model
{
    protected $table = 'player_stats';

    protected $fillable = [
        'player_id',
        'quick_games',
        'quick_avg_three_darts',
        'quick_highest_hf',
        'quick_fastest_qf',
        'quick_count_max',
        'quick_count_170_plus',
        'quick_count_hf',
        'quick_count_qf',
        'tournament_games',
        'tournament_avg_three_darts',
        'tournament_highest_hf',
        'tournament_fastest_qf',
        'tournament_count_max',
        'tournament_count_170_plus',
        'tournament_count_hf',
        'tournament_count_qf',
    ];

    protected $casts = [
        'quick_avg_three_darts' => 'float',
        'tournament_avg_three_darts' => 'float',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}


