<?php

namespace App\Models\Push;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPushDispatch extends Model
{
    protected $fillable = [
        'admin_id',
        'title',
        'body',
        'audience',
        'recipient_count',
        'test_only',
        'sent_at',
    ];

    protected $casts = [
        'recipient_count' => 'integer',
        'test_only' => 'boolean',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
