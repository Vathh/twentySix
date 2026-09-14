<?php

namespace App\Models\Tournament;

use App\Enums\GameStage;
use App\Models\Player\Player;
use App\Models\Season\Season;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentResult extends Model
{
    protected $fillable = [
        'season_id',
        'tournament_id',
        'player_id',
        'points',
        'place',
        'elimination_stage',
    ];

    protected $casts = [
        'elimination_stage' => GameStage::class,
    ];

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

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
