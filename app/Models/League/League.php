<?php

namespace App\Models\League;

use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    public $timestamps = true;
    protected $fillable = ['name', 'description', 'match_format_presets'];

    protected function casts(): array
    {
        return [
            'match_format_presets' => 'array',
        ];
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_user_admin');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function relatedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_user');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Player::class);
    }
}


