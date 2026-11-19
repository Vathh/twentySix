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
        'duration_seconds',
        'dart_count',
        'published_at',
    ];

    protected $casts = [
        'kind' => TrainingKind::class,
        'status' => TrainingDefinitionStatus::class,
        'difficulty' => TrainingDifficulty::class,
        'declared_minutes' => 'integer',
        'duration_seconds' => 'integer',
        'dart_count' => 'integer',
        'published_at' => 'datetime',
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
