<?php

namespace App\Models\League;

use App\Enums\OrganizationInvitationStatus;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueInvitation extends Model
{
    protected $fillable = [
        'league_id',
        'user_id',
        'invited_by',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'status' => OrganizationInvitationStatus::class,
        'responded_at' => 'datetime',
    ];

    /** @return BelongsTo<League, $this> */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
