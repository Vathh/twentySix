<?php

namespace App\Http\Requests\Admin;

use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainingDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $slug = $this->kindSlug();

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'difficulty' => [
                Rule::requiredIf($slug === 'plans'),
                'nullable',
                'string',
                'max:16',
            ],
            'declared_minutes' => [
                Rule::requiredIf($slug === 'plans'),
                'nullable',
                'integer',
                Rule::in([30, 60, 90, 120]),
            ],
            'mechanic' => ['nullable', 'string', 'max:64'],
            'items' => ['nullable', 'array', 'max:80'],
            'items.*.kind' => ['nullable', 'string', 'max:16'],
            'items.*.exercise_key' => ['nullable', 'string', 'max:64'],
            'items.*.break_seconds' => ['nullable', 'integer', 'min:1', 'max:7200'],
            'items.*.duration_seconds' => ['nullable', 'integer', 'min:1', 'max:14400'],
            'items.*.dart_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nazwa',
            'description' => 'opis',
            'difficulty' => 'poziom trudności',
            'declared_minutes' => 'deklarowany czas',
            'mechanic' => 'mechanika',
            'items.*.duration_seconds' => 'czas',
            'items.*.dart_count' => 'liczba lotek',
            'items.*.exercise_key' => 'ćwiczenie',
            'items' => 'elementy',
            'image' => 'grafika',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function definitionPayload(TrainingKind $kind): array
    {
        $payload = [
            'kind' => $kind->value,
            'name' => trim($this->string('name')->toString()),
            'description' => $this->input('description'),
        ];

        if ($kind === TrainingKind::Plan) {
            $payload['difficulty'] = $this->input('difficulty');
            $payload['declaredMinutes'] = $this->input('declared_minutes');
        }

        if ($kind === TrainingKind::Challenge && $this->filled('mechanic')) {
            $payload['mechanic'] = $this->input('mechanic');
        }

        $payload['items'] = $this->itemPayload();

        return $payload;
    }

    public function removesImage(): bool
    {
        return $this->boolean('remove_image');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemPayload(): array
    {
        $rows = $this->input('items', []);
        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $kind = $row['kind'] ?? null;
            if (! is_string($kind) || $kind === '') {
                continue;
            }

            if ($kind === 'break') {
                $items[] = [
                    'kind' => 'break',
                    'breakSeconds' => $row['break_seconds'] ?? null,
                ];

                continue;
            }

            $item = [
                'kind' => 'exercise',
                'exerciseKey' => $row['exercise_key'] ?? null,
            ];
            if (($row['duration_seconds'] ?? '') !== '') {
                $item['durationSeconds'] = $row['duration_seconds'];
            }
            if (($row['dart_count'] ?? '') !== '') {
                $item['dartCount'] = $row['dart_count'];
            }
            $items[] = $item;
        }

        return $items;
    }

    private function kindSlug(): ?string
    {
        $slug = $this->route('kind');
        if (is_string($slug)) {
            return $slug;
        }

        $definition = $this->route('definition');
        if ($definition instanceof TrainingDefinition) {
            return $definition->kind->slug();
        }

        return null;
    }
}
