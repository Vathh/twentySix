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
        'bracket_side',
        'stage',
        'starting_score',
        'legs_to_win_set',
        'sets_to_win_match',
        'game_type',
        'dart_limit',
        'loss_threshold',
    ];

    protected $casts = [
        'bracket_side' => \App\Enums\BracketSide::class,
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
