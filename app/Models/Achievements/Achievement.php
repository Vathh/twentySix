<?php

namespace App\Models\Achievements;

use App\Enums\AchievementType;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    protected $fillable = [
        'tournament_id',
        'player_id',
        'type',
        'value',
    ];

    protected $casts = [
        'type' => AchievementType::class,
    ];

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
