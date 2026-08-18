<?php

namespace App\Models\League;

use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueMatchdayPlanning;
use App\Enums\LeagueSeasonStatus;
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
        'start_date',
        'end_date',
        'deadline_at',
        'random_seed',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeagueSeasonStatus::class,
            'calendar_mode' => LeagueCalendarMode::class,
            'matchday_planning' => LeagueMatchdayPlanning::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'deadline_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'rounds_each' => 'integer',
            'matchday_length_days' => 'integer',
            'random_seed' => 'integer',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(LeagueSeasonDivision::class)->orderBy('position');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(LeagueSeasonParticipant::class);
    }

    public function matchdays(): HasMany
    {
        return $this->hasMany(LeagueSeasonMatchday::class)->orderBy('round_number');
    }

    public function games(): HasMany
    {
        return $this->hasMany(LeagueGame::class);
    }
}
