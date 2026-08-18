<?php

namespace App\Models\Season;

use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{

    protected static function booted(): void
    {
        static::created(function ($season) {
            $season->organization?->touch();
        });

        static::updated(function ($season) {
            $season->organization?->touch();
        });
    }

    protected $fillable = [
      'organization_id',
      'name',
      'start_date',
      'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date'
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'season_user_admin', 'season_id', 'user_id');
    }

    public function relatedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'season_user');
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Player::class);
    }
}


