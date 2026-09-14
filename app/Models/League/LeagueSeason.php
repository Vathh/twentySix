<?php

namespace App\Models\League;

use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueMatchdayPlanning;
use App\Enums\LeagueSeasonStatus;
use App\Enums\MatchWinMode;
use App\Models\Player\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueSeason extends Model
{
    protected $fillable = [
        'league_id',
        'name',
        'status',
        'calendar_mode',
        'rounds_each',
        'matchday_length_days',
        'matchday_planning',
        'allows_draws',
        'win_mode',
        'win_length',
        'start_date',
        'end_date',
        'deadline_at',
        'random_seed',
        'started_at',
        'finished_at',
        'champion_player_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeagueSeasonStatus::class,
            'calendar_mode' => LeagueCalendarMode::class,
            'matchday_planning' => LeagueMatchdayPlanning::class,
            'win_mode' => MatchWinMode::class,
            'allows_draws' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'deadline_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'win_length' => 'integer',
            'rounds_each' => 'integer',
            'matchday_length_days' => 'integer',
            'random_seed' => 'integer',
        ];
    }

    /** @return BelongsTo<League, $this> */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /** @return HasMany<LeagueSeasonDivision, $this> */
    public function divisions(): HasMany
    {
        return $this->hasMany(LeagueSeasonDivision::class)->orderBy('position');
    }

    /** @return HasMany<LeagueSeasonParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(LeagueSeasonParticipant::class);
    }

    /** @return HasMany<LeagueSeasonMatchday, $this> */
    public function matchdays(): HasMany
    {
        return $this->hasMany(LeagueSeasonMatchday::class)->orderBy('round_number');
    }

    /** @return HasMany<LeagueGame, $this> */
    public function games(): HasMany
    {
        return $this->hasMany(LeagueGame::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function champion(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'champion_player_id');
    }
}
