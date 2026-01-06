<?php

namespace App\Models;

use App\Enums\GameStatus;
use App\Enums\PlayoffRound;
use App\Enums\PlayoffSlot;
use App\Enums\WinnerDestinationSlot;
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
        'winner_id',
        'winner_destination_slot',
        'status'
    ];

    protected $casts = [
        'round' => PlayoffRound::class,
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
