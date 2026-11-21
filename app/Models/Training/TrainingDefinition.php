<?php

namespace App\Models\Training;

use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingDifficulty;
use App\Domain\Training\TrainingKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingDefinition extends Model
{
    protected $fillable = [
        'kind',
        'status',
        'name',
        'description',
        'image_path',
        'difficulty',
        'declared_minutes',
        'mechanic',
        'badge_thresholds',
        'duration_seconds',
        'dart_count',
        'published_at',
        'starts_at',
        'ends_at',
        'attempts_per_window',
        'window_mode',
        'window_anchor_date',
        'attempt_timeout_hours',
        'paused_at',
        'closing_at',
        'finished_at',
    ];

    protected $casts = [
        'kind' => TrainingKind::class,
        'status' => TrainingDefinitionStatus::class,
        'difficulty' => TrainingDifficulty::class,
        'declared_minutes' => 'integer',
        'badge_thresholds' => 'array',
        'duration_seconds' => 'integer',
        'dart_count' => 'integer',
        'published_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'attempts_per_window' => 'integer',
        'window_anchor_date' => 'date',
        'attempt_timeout_hours' => 'integer',
        'paused_at' => 'datetime',
        'closing_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return HasMany<TrainingDefinitionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TrainingDefinitionItem::class, 'definition_id')->orderBy('position');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function imageUrl(): ?string
    {
        if (! is_string($this->image_path) || $this->image_path === '') {
            return null;
        }

        $version = $this->updated_at?->getTimestamp() ?? 0;

        return url('/storage/'.ltrim($this->image_path, '/')).'?v='.$version;
    }
}
