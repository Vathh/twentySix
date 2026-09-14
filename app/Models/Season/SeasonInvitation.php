<?php

namespace App\Models\Season;

use App\Enums\OrganizationInvitationStatus;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonInvitation extends Model
{
    protected $fillable = [
        'season_id',
        'user_id',
        'invited_by',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'status' => OrganizationInvitationStatus::class,
        'responded_at' => 'datetime',
    ];

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
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
