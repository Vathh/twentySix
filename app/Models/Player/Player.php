<?php

namespace App\Models\Player;

use App\Models\Achievements\Achievement;
use App\Models\League\League;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    protected $fillable = ['name', 'description', 'user_id', 'is_bye', 'organization_id', 'season_id', 'league_id'];

    protected $casts = [
        'is_bye' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return '?';
        }

        $letter = static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1));

        if (count($parts) === 1) {
            $word = $parts[0];

            return mb_strtoupper(mb_substr($word, 0, min(2, mb_strlen($word))));
        }

        return $letter($parts[0]).$letter($parts[array_key_last($parts)]);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    public function playerStat(): HasOne
    {
        return $this->hasOne(PlayerStat::class);
    }

    public function playerOverviewStat(): HasOne
    {
        return $this->hasOne(PlayerOverviewStat::class);
    }
}
