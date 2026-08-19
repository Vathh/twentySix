<?php

namespace App\Models\Organization;

use App\Enums\OrganizationInvitationStatus;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInvitation extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'invited_by',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'status' => OrganizationInvitationStatus::class,
        'responded_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
