<?php

namespace App\Models\Game;

use App\Enums\GameStatus;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    protected $fillable = [
        'tournament_id',
        'player1_id',
        'player2_id',
        'player1_score',
        'player2_score',
        'player1_legs_in_set',
        'player2_legs_in_set',
        'current_set_number',
        'winner_id',
        'group_number',
        'status',
        'starting_score',
        'legs_to_win_set',
        'sets_to_win_match',
        'game_type',
    ];

    protected $casts = [
        'status' => GameStatus::class
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }
}


