<?php

namespace App\Models\Organization;

use App\Models\League\League;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use SoftDeletes;

    public $timestamps = true;

    protected $fillable = ['name', 'description', 'logo_path', 'match_format_presets'];

    protected function casts(): array
    {
        return [
            'match_format_presets' => 'array',
        ];
    }

    public function logoUrl(): ?string
    {
        if (! is_string($this->logo_path) || $this->logo_path === '') {
            return null;
        }

        $version = $this->updated_at?->getTimestamp() ?? 0;
        $url = url('/storage/'.ltrim($this->logo_path, '/'));

        return $version > 0 ? $url.'?v='.$version : $url;
    }

    /** @return BelongsToMany<User, $this> */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user_admin');
    }

    /** @return HasMany<Season, $this> */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    /** @return HasManyThrough<Tournament, Season, $this> */
    public function tournaments(): HasManyThrough
    {
        return $this->hasManyThrough(Tournament::class, Season::class);
    }

    /**
     * Turnieje jednorazowe: należą do organizacji, nie do sezonu.
     *
     * @return HasMany<Tournament, $this>
     */
    public function oneOffTournaments(): HasMany
    {
        return $this->hasMany(Tournament::class)->whereNull('season_id');
    }

    /** @return HasMany<League, $this> */
    public function leagues(): HasMany
    {
        return $this->hasMany(League::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function relatedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user');
    }

    /** @return HasMany<OrganizationInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /** @return HasMany<Player, $this> */
    public function guests(): HasMany
    {
        return $this->hasMany(Player::class);
    }
}
