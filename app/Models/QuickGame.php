<?php

namespace App\Models;

use App\Enums\GameStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickGame extends Model
{
    protected $fillable = [
        'player1_id',
        'player2_id',
        'player1_score',
        'player2_score',
        'winner_id',
        'status'
    ];

    protected $casts = [
        'status' => GameStatus::class
    ];

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
