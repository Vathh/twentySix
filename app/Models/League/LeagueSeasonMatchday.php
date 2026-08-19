<?php

namespace App\Models\League;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueSeasonMatchday extends Model
{
    protected $fillable = [
        'league_season_id',
        'round_number',
        'window_start',
        'window_end',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'round_number' => 'integer',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(LeagueSeason::class, 'league_season_id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(LeagueGame::class);
    }

    public function windowLabel(): string
    {
        return $this->window_start->format('d.m.Y').' – '.$this->window_end->format('d.m.Y');
    }

    public function isCurrent(?CarbonInterface $at = null): bool
    {
        $today = Carbon::parse($at ?? now())->toDateString();

        return $today >= $this->window_start->toDateString()
            && $today <= $this->window_end->toDateString();
    }
}
