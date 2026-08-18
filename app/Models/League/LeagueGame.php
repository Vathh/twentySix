<?php

namespace App\Models\League;

use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueWalkoverType;
use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueGame extends Model
{
    protected $fillable = [
        'league_season_id',
        'league_season_division_id',
        'higher_season_division_id',
        'lower_season_division_id',
        'league_season_matchday_id',
        'purpose',
        'player1_id',
        'player2_id',
        'player1_score',
        'player2_score',
        'winner_id',
        'status',
        'walkover_type',
        'deadline_at',
        'starting_score',
        'legs_to_win_set',
        'sets_to_win_match',
        'game_type',
        'tie_group_key',
        'bracket_round',
        'is_third_place',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => LeagueGamePurpose::class,
            'status' => LeagueGameStatus::class,
            'walkover_type' => LeagueWalkoverType::class,
            'deadline_at' => 'datetime',
            'is_third_place' => 'boolean',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(LeagueSeason::class, 'league_season_id');
    }

    public function seasonDivision(): BelongsTo
    {
        return $this->belongsTo(LeagueSeasonDivision::class, 'league_season_division_id');
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(LeagueSeasonMatchday::class, 'league_season_matchday_id');
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
