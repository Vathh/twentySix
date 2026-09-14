<?php

namespace App\Models\Tournament;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentMatchFormat extends Model
{
    protected $fillable = [
        'tournament_id',
        'stage',
        'starting_score',
        'legs_to_win_set',
        'sets_to_win_match',
        'game_type',
    ];

    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function toMatchFormat(): MatchFormat
    {
        return MatchFormat::fromRecord($this);
    }

    public function stageEnum(): GameStage
    {
        return GameStage::from($this->stage);
    }
}
