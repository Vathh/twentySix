<?php

namespace App\Models\Webcam;

use App\Enums\WebcamMatchStatus;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebcamMatch extends Model
{
    protected $fillable = [
        'host_user_id',
        'guest_user_id',
        'status',
        'leg_number',
        'current_turn_user_id',
        'host_legs_won',
        'guest_legs_won',
        'host_ready_at',
        'guest_ready_at',
        'winner_user_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'status' => WebcamMatchStatus::class,
        'host_ready_at' => 'datetime',
        'guest_ready_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    /** @return HasMany<WebcamMatchVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(WebcamMatchVisit::class)->orderBy('id');
    }

    public function hasParticipant(int $userId): bool
    {
        return (int) $this->host_user_id === $userId || (int) $this->guest_user_id === $userId;
    }

    public function opponentUserId(int $userId): int
    {
        return (int) $this->host_user_id === $userId
            ? (int) $this->guest_user_id
            : (int) $this->host_user_id;
    }

    public function isReady(int $userId): bool
    {
        if ((int) $this->host_user_id === $userId) {
            return $this->host_ready_at !== null;
        }

        return $this->guest_ready_at !== null;
    }
}
