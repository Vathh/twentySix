<?php

namespace App\Models\Tournament;

use App\Enums\GameStage;
use App\Support\GameScoring\MatchFormat;
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
