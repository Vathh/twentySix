<?php

namespace App\Models\Activity;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReaction extends Model
{
    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'kind',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
