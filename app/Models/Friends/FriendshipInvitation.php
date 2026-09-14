<?php

namespace App\Models\Friends;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FriendshipInvitation extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
