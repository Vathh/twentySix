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
            'badge_iron' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'badge_bronze' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'badge_gold' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'badge_platinum' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'badge_diamond' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'attempts_per_window' => ['nullable', 'integer', 'min:1', 'max:20'],
            'window_mode' => ['nullable', 'string', 'max:32'],
            'window_anchor_date' => ['nullable', 'date'],
            'attempt_timeout_hours' => ['nullable', 'integer', 'min:1', 'max:72'],
            'items' => ['nullable', 'array', 'max:80'],
            'items.*.kind' => ['nullable', 'string', 'max:16'],
            'items.*.exercise_key' => ['nullable', 'string', 'max:64'],
            'items.*.break_seconds' => ['nullable', 'integer', 'min:1', 'max:7200'],
            'items.*.duration_seconds' => ['nullable', 'integer', 'min:1', 'max:14400'],
            'items.*.dart_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'items.*.scored' => ['nullable', 'boolean'],
            'items.*.variant' => ['nullable', 'string', 'max:32'],
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
            'badge_iron' => 'próg Żelazo',
            'badge_bronze' => 'próg Brąz',
            'badge_gold' => 'próg Złoto',
            'badge_platinum' => 'próg Platyna',
            'badge_diamond' => 'próg Diament',
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

        if ($kind === TrainingKind::Challenge) {
            $hasBadge = $this->filled('badge_iron')
                || $this->filled('badge_bronze')
                || $this->filled('badge_gold')
                || $this->filled('badge_platinum')
                || $this->filled('badge_diamond');
            if ($hasBadge) {
                $payload['badgeThresholds'] = [
                    'iron' => $this->input('badge_iron'),
                    'bronze' => $this->input('badge_bronze'),
                    'gold' => $this->input('badge_gold'),
                    'platinum' => $this->input('badge_platinum'),
                    'diamond' => $this->input('badge_diamond'),
                ];
            }
        }

        if ($kind === TrainingKind::Event) {
            $payload['startsAt'] = $this->input('starts_at');
            $payload['endsAt'] = $this->input('ends_at');
            $payload['attemptsPerWindow'] = $this->input('attempts_per_window');
            $payload['windowMode'] = $this->input('window_mode');
            $payload['windowAnchorDate'] = $this->input('window_anchor_date');
            $payload['attemptTimeoutHours'] = $this->input('attempt_timeout_hours');
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
                'scored' => ! in_array($row['scored'] ?? true, [false, 0, '0', 'false', ''], true),
            ];
            if (($row['duration_seconds'] ?? '') !== '') {
                $item['durationSeconds'] = $row['duration_seconds'];
            }
            if (($row['dart_count'] ?? '') !== '') {
                $item['dartCount'] = $row['dart_count'];
            }
            if (($row['exercise_key'] ?? null) === 'scoring_under_pressure') {
                $item['variant'] = ($row['variant'] ?? '') !== '' ? $row['variant'] : '20';
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
