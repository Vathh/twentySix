<?php

namespace App\Models\Training;

use App\Domain\Training\TrainingEventAttemptStatus;
use App\Models\Concerns\HidesWipedTraining;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingEventAttempt extends Model
{
    use HidesWipedTraining;

    protected $fillable = [
        'user_id',
        'definition_id',
        'client_attempt_id',
        'status',
        'window_key',
        'started_at',
        'expires_at',
        'completed_at',
        'voided_at',
        'void_reason',
        'score',
        'stats',
        'wipe_id',
    ];

    protected $casts = [
        'status' => TrainingEventAttemptStatus::class,
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'voided_at' => 'datetime',
        'score' => 'integer',
        'stats' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TrainingDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(TrainingDefinition::class, 'definition_id');
    }

    /** @return BelongsTo<TrainingHistoryWipe, $this> */
    public function wipe(): BelongsTo
    {
        return $this->belongsTo(TrainingHistoryWipe::class, 'wipe_id');
    }
}
