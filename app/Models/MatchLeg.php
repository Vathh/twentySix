<?php

namespace App\Models;

use App\Models\QuickGame;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchLeg extends Model
{
    protected $fillable = [
        'game_id',
        'playoff_game_id',
        'quick_game_id',
        'leg_number',
        'player1_score',
        'player2_score',
        'winner_id',
        'player1_average',
        'player2_average',
        'player1_darts_thrown',
        'player2_darts_thrown',
        'checkout_score',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function playoffGame(): BelongsTo
    {
        return $this->belongsTo(PlayoffGame::class, 'playoff_game_id');
    }

    public function quickGame(): BelongsTo
    {
        return $this->belongsTo(QuickGame::class, 'quick_game_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }
}
