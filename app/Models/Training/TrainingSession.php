<?php

namespace App\Models\Training;

use App\Domain\Training\TrainingKind;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingSession extends Model
{
    protected $fillable = [
        'user_id',
        'client_session_id',
        'definition_id',
        'exercise_key',
        'kind',
        'completed_at',
        'score',
        'stats',
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
}
