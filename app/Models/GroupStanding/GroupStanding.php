<?php

namespace App\Models\GroupStanding;

use App\Enums\GameStatus;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupStanding extends Model
{
    protected $fillable = [
        'tournament_id',
        'group_number',
        'player_id',
        'games_played',
        'games_won',
        'games_lost',
        'legs_won',
        'legs_lost',
        'points',
        'place',
    ];

    protected $casts = [
        'status' => GameStatus::class
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}


