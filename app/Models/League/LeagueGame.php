<?php

namespace App\Models\League;

use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueWalkoverType;
use App\Enums\MatchWinMode;
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
        'lobby_host_player_id',
        'opponent_accepted_at',
        'scoring_host_player_id',
        'starting_score',
        'legs_to_win_set',
        'sets_to_win_match',
        'game_type',
        'win_mode',
        'win_length',
        'tie_group_key',
        'bracket_round',
        'is_third_place',
        'dart_limit',
        'loss_threshold',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => LeagueGamePurpose::class,
            'status' => LeagueGameStatus::class,
            'walkover_type' => LeagueWalkoverType::class,
            'deadline_at' => 'datetime',
            'is_third_place' => 'boolean',
            'win_mode' => MatchWinMode::class,
            'opponent_accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LeagueSeason, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(LeagueSeason::class, 'league_season_id');
    }

    /** @return BelongsTo<LeagueSeasonDivision, $this> */
    public function seasonDivision(): BelongsTo
    {
        return $this->belongsTo(LeagueSeasonDivision::class, 'league_season_division_id');
    }

    /** @return BelongsTo<LeagueSeasonMatchday, $this> */
    public function matchday(): BelongsTo
    {
        return $this->belongsTo(LeagueSeasonMatchday::class, 'league_season_matchday_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player1(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player1_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player2(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player2_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function scoringHost(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'scoring_host_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function lobbyHost(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'lobby_host_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }
}
