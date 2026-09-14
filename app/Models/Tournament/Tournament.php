<?php

namespace App\Models\Tournament;

use App\Enums\TournamentStatus;
use App\Models\Achievements\Achievement;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\PointScheme\PointScheme;
use App\Models\Season\Season;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = [
        'name',
        'season_id',
        'date',
        'status',
        'format',
        'grand_final_mode',
        'point_scheme_id',
        'groups_count',
        'playoff_bracket_size',
        'group_advances',
        'tablets_count',
        'join_code',
        'join_code_generated_at',
        'join_code_enabled',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => TournamentStatus::class,
        'format' => \App\Enums\TournamentFormat::class,
        'grand_final_mode' => \App\Enums\GrandFinalMode::class,
        'group_advances' => 'array',
        'join_code_generated_at' => 'datetime',
        'join_code_enabled' => 'boolean',
    ];

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** @return HasMany<Achievement, $this> */
    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    /** @return HasMany<Game, $this> */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /** @return HasMany<PlayoffGame, $this> */
    public function playoffGames(): HasMany
    {
        return $this->hasMany(PlayoffGame::class);
    }

    /** @return HasMany<GroupStanding, $this> */
    public function groupStandings(): HasMany
    {
        return $this->hasMany(GroupStanding::class);
    }

    /** @return BelongsTo<PointScheme, $this> */
    public function pointScheme(): BelongsTo
    {
        return $this->belongsTo(PointScheme::class, 'point_scheme_id');
    }

    /** @return HasMany<TournamentResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(TournamentResult::class);
    }

    /** @return HasMany<TournamentInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(TournamentInvitation::class);
    }

    /** @return HasMany<TournamentGuestParticipant, $this> */
    public function guestParticipants(): HasMany
    {
        return $this->hasMany(TournamentGuestParticipant::class);
    }

    /** @return HasMany<TournamentJoinRequest, $this> */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(TournamentJoinRequest::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_user_admin', 'tournament_id', 'user_id');
    }
}
