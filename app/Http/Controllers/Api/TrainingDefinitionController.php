<?php

namespace App\Http\Controllers\Api;

use App\Models\Training\TrainingDefinition;
use App\Models\Training\TrainingDefinitionItem;
use App\Models\Users\User;
use App\Services\Training\TrainingDefinitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingDefinitionController
{
    public function __construct(
        private TrainingDefinitionService $definitions,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'kind' => ['required', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'difficulty' => ['nullable', 'string', 'max:16'],
            'declaredMinutes' => ['nullable', 'integer', Rule::in([30, 60, 90, 120])],
            'mechanic' => ['nullable', 'string', 'max:64'],
            'badgeThresholds' => ['nullable', 'array'],
            'badgeThresholds.iron' => ['nullable', 'integer', 'min:0', 'max:3190'],
            'badgeThresholds.bronze' => ['nullable', 'integer', 'min:0', 'max:3190'],
            'badgeThresholds.gold' => ['nullable', 'integer', 'min:0', 'max:3190'],
            'badgeThresholds.platinum' => ['nullable', 'integer', 'min:0', 'max:3190'],
            'badgeThresholds.diamond' => ['nullable', 'integer', 'min:0', 'max:3190'],
            'durationSeconds' => ['nullable', 'integer', 'min:1', 'max:14400'],
            'dartCount' => ['nullable', 'integer', 'min:1', 'max:500'],
            'items' => ['nullable', 'array', 'max:80'],
            'items.*.kind' => ['required', 'string', 'max:16'],
            'items.*.exerciseKey' => ['nullable', 'string', 'max:64'],
            'items.*.breakSeconds' => ['nullable', 'integer', 'min:1', 'max:7200'],
            'items.*.durationSeconds' => ['nullable', 'integer', 'min:1', 'max:14400'],
            'items.*.dartCount' => ['nullable', 'integer', 'min:1', 'max:500'],
            'items.*.scored' => ['nullable', 'boolean'],
            'items.*.variant' => ['nullable', 'string', 'max:32'],
        ]);

        $definition = $this->definitions->create($this->user($request), $request->all());

        return response()->json($this->payload($definition), 201);
    }

    public function activate(Request $request, int $definition): JsonResponse
    {
        $updated = $this->definitions->activate($this->user($request), $definition);

        return response()->json($this->payload($updated));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TrainingDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'kind' => $definition->kind->value,
            'status' => $definition->status->value,
            'name' => $definition->name,
            'description' => $definition->description,
            'imagePath' => $definition->image_path,
            'difficulty' => $definition->difficulty?->value,
            'declaredMinutes' => $definition->declared_minutes,
            'challengeKey' => $definition->kind->value === 'challenge' ? $definition->mechanic : null,
            'badgeThresholds' => $definition->badge_thresholds,
            'durationSeconds' => $definition->duration_seconds,
            'dartCount' => $definition->dart_count,
            'publishedAt' => $definition->published_at?->toIso8601String(),
            'items' => $definition->items->map(fn (TrainingDefinitionItem $item): array => [
                'position' => $item->position,
                'kind' => $item->item_kind->value,
                'exerciseKey' => $item->exercise_key,
                'exerciseName' => $item->exercise_key ? $item->exerciseLabel() : null,
                'durationSeconds' => $item->duration_seconds,
                'dartCount' => $item->dart_count,
                'breakSeconds' => $item->break_seconds,
                'scored' => (bool) $item->scored,
                'variant' => $item->variant,
            ])->values()->all(),
        ];
    }
}
