<?php

namespace App\Models\Training;

use App\Models\Concerns\HidesWipedTraining;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingChallengePublication extends Model
{
    use HidesWipedTraining;

    protected $fillable = [
        'user_id',
        'definition_id',
        'session_id',
        'score',
        'published_at',
        'wipe_id',
    ];

    protected $casts = [
        'score' => 'integer',
        'published_at' => 'datetime',
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

    /** @return BelongsTo<TrainingSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'session_id');
    }

    /** @return BelongsTo<TrainingHistoryWipe, $this> */
    public function wipe(): BelongsTo
    {
        return $this->belongsTo(TrainingHistoryWipe::class, 'wipe_id');
    }
}
