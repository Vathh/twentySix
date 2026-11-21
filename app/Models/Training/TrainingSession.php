<?php

namespace App\Models\Training;

use App\Domain\Training\TrainingKind;
use App\Models\Concerns\HidesWipedTraining;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingSession extends Model
{
    use HidesWipedTraining;

    protected $fillable = [
        'user_id',
        'client_session_id',
        'definition_id',
        'exercise_key',
        'kind',
        'completed_at',
        'score',
        'stats',
        'wipe_id',
    ];

    protected $casts = [
        'kind' => TrainingKind::class,
        'completed_at' => 'datetime',
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
