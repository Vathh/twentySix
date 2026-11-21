<?php

namespace App\Models\Training;

use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingItemKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingDefinitionItem extends Model
{
    protected $fillable = [
        'definition_id',
        'position',
        'item_kind',
        'exercise_key',
        'duration_seconds',
        'dart_count',
        'scored',
        'variant',
        'break_seconds',
    ];

    protected $casts = [
        'item_kind' => TrainingItemKind::class,
        'duration_seconds' => 'integer',
        'dart_count' => 'integer',
        'scored' => 'boolean',
        'break_seconds' => 'integer',
    ];

    /** @return BelongsTo<TrainingDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(TrainingDefinition::class, 'definition_id');
    }

    public function exerciseLabel(): string
    {
        if (! is_string($this->exercise_key) || $this->exercise_key === '') {
            return 'Ćwiczenie';
        }

        return TrainingExerciseCatalog::label($this->exercise_key);
    }
}
