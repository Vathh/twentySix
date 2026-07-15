<?php

namespace App\Models\PlayoffGame;

use App\Enums\GameStatus;
use App\Enums\GameStage;
use App\Enums\PlayoffSlot;
use App\Enums\WinnerDestinationSlot;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayoffGame extends Model
{
    protected $fillable = [
        'tournament_id',
        'round',
        'slot',
        'player1_id',
        'player2_id',
        'player1_score',
        'player2_score',
        'player1_legs_in_set',
        'player2_legs_in_set',
        'current_set_number',
        'winner_id',
        'winner_destination_slot',
        'status',
        'starting_score',
        'legs_to_win_set',
        'sets_to_win_match',
        'game_type',
    ];

    protected $casts = [
        'round' => GameStage::class,
        'slot' => PlayoffSlot::class,
        'winner_destination_slot' => WinnerDestinationSlot::class,
        'status' => GameStatus::class,
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


