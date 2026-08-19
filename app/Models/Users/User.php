<?php

namespace App\Models\Users;

use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_create_organizations' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    public function adminOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user_admin');
    }

    public function relatedOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user');
    }

    public function adminSeasons(): BelongsToMany
    {
        return $this->belongsToMany(Season::class, 'season_user_admin');
    }

    public function relatedSeasons(): BelongsToMany
    {
        return $this->belongsToMany(Season::class, 'season_user');
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }
}


