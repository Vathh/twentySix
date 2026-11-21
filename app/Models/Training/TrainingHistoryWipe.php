<?php

namespace App\Models\Training;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingHistoryWipe extends Model
{
    protected $fillable = [
        'user_id',
        'wiped_at',
        'purge_after',
        'restored_at',
    ];

    protected $casts = [
        'wiped_at' => 'datetime',
        'purge_after' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function isOpen(): bool
    {
        return $this->restored_at === null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TrainingSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'wipe_id');
    }

    /** @return HasMany<TrainingChallengePublication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(TrainingChallengePublication::class, 'wipe_id');
    }

    /** @return HasMany<TrainingEventAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingEventAttempt::class, 'wipe_id');
    }
}
