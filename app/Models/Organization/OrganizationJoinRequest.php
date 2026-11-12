<?php

namespace App\Models\Organization;

use App\Enums\OrganizationJoinRequestStatus;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationJoinRequest extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'status' => OrganizationJoinRequestStatus::class,
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
