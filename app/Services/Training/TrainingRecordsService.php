<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingExerciseVariant;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use Carbon\CarbonImmutable;

class TrainingRecordsService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        return $this->bundle($user)['items'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitionsForUser(User $user): array
    {
        return $this->bundle($user)['definitions'];
    }

    /**
     * @return array{items: list<array<string, mixed>>, definitions: list<array<string, mixed>>}
     */
    public function bundle(User $user): array
    {
        $bestExercises = [];
        $bestDefinitions = [];

        $sessions = TrainingSession::query()
            ->where('user_id', $user->id)
            ->whereIn('kind', [
                TrainingKind::Exercise->value,
                TrainingKind::Plan->value,
                TrainingKind::Challenge->value,
                TrainingKind::Event->value,
            ])
            ->orderBy('completed_at')
            ->get(['exercise_key', 'kind', 'definition_id', 'score', 'stats', 'completed_at']);

        foreach ($sessions as $session) {
            if ($session->kind === TrainingKind::Exercise) {
                $this->considerExercise(
                    $bestExercises,
                    $session->exercise_key,
                    $this->variantFromStats($session->exercise_key, $session->stats),
                    $session->score,
                    $session->completed_at,
                );

                continue;
            }

            if ($session->definition_id !== null) {
                $this->considerDefinition(
                    $bestDefinitions,
                    (int) $session->definition_id,
                    $session->kind->value,
                    $session->score,
                    $session->completed_at,
                );
            }

            if ($session->kind !== TrainingKind::Plan && $session->kind !== TrainingKind::Event) {
                continue;
            }

            $items = is_array($session->stats) ? ($session->stats['items'] ?? null) : null;
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (($item['kind'] ?? null) !== 'exercise') {
                    continue;
                }
                if (($item['status'] ?? null) !== 'completed') {
                    continue;
                }
                $key = $item['exerciseKey'] ?? null;
                $score = $item['score'] ?? null;
                $variant = $this->variantFromItem($key, $item);
                $this->considerExercise($bestExercises, is_string($key) ? $key : null, $variant, $score, $session->completed_at);
            }
        }

        $rows = [];
        foreach ($bestExercises as $row) {
            $rows[] = [
                'exerciseKey' => $row['exerciseKey'],
                'variant' => $row['variant'],
                'name' => TrainingExerciseVariant::displayName(
                    $row['exerciseKey'],
                    (string) ($row['variant'] ?? ''),
                ),
                'score' => $row['score'],
                'achievedAt' => $row['achievedAt'],
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $byName = strcmp($a['name'], $b['name']);
            if ($byName !== 0) {
                return $byName;
            }

            return strcmp((string) ($a['variant'] ?? ''), (string) ($b['variant'] ?? ''));
        });

        $definitions = array_values($bestDefinitions);
        usort($definitions, static fn (array $a, array $b): int => $a['definitionId'] <=> $b['definitionId']);

        return [
            'items' => $rows,
            'definitions' => $definitions,
        ];
    }

    /**
     * @param  array<string, array{exerciseKey: string, variant: string|null, score: int, achievedAt: string}>  $best
     */
    private function considerExercise(array &$best, ?string $exerciseKey, ?string $variant, mixed $score, mixed $completedAt): void
    {
        if ($exerciseKey === null || TrainingExerciseCatalog::parameters($exerciseKey) === null) {
            return;
        }
        if (! is_int($score) && ! (is_string($score) && preg_match('/^-?\d+$/', $score) === 1)) {
            return;
        }
        $value = (int) $score;
        $at = $this->iso($completedAt);
        $bucket = $exerciseKey."\0".($variant ?? '');

        if (! isset($best[$bucket]) || $value > $best[$bucket]['score']) {
            $best[$bucket] = [
                'exerciseKey' => $exerciseKey,
                'variant' => $variant,
                'score' => $value,
                'achievedAt' => $at,
            ];
        }
    }

    /**
     * @param  array<int, array{definitionId: int, kind: string, score: int, achievedAt: string}>  $best
     */
    private function considerDefinition(array &$best, int $definitionId, string $kind, mixed $score, mixed $completedAt): void
    {
        if ($definitionId <= 0) {
            return;
        }
        if (! is_int($score) && ! (is_string($score) && preg_match('/^-?\d+$/', $score) === 1)) {
            return;
        }
        $value = (int) $score;
        $at = $this->iso($completedAt);

        if (! isset($best[$definitionId]) || $value > $best[$definitionId]['score']) {
            $best[$definitionId] = [
                'definitionId' => $definitionId,
                'kind' => $kind,
                'score' => $value,
                'achievedAt' => $at,
            ];
        }
    }

    private function variantFromStats(?string $exerciseKey, mixed $stats): ?string
    {
        $raw = is_array($stats) ? ($stats['variant'] ?? null) : null;

        return $this->normalizeVariant($exerciseKey, is_string($raw) ? $raw : null);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function variantFromItem(?string $exerciseKey, array $item): ?string
    {
        $raw = $item['variant'] ?? null;
        if (! is_string($raw) && is_array($item['stats'] ?? null)) {
            $fromStats = $item['stats']['variant'] ?? null;
            $raw = is_string($fromStats) ? $fromStats : null;
        }

        return $this->normalizeVariant($exerciseKey, is_string($raw) ? $raw : null);
    }

    private function normalizeVariant(?string $exerciseKey, ?string $variant): ?string
    {
        if ($exerciseKey === null) {
            return null;
        }
        $allowed = TrainingExerciseVariant::allowed($exerciseKey);
        if ($allowed === null) {
            return null;
        }
        $normalized = TrainingExerciseVariant::normalize($variant);
        if ($normalized !== '' && in_array($normalized, $allowed, true)) {
            return $normalized;
        }

        return $allowed[0] ?? null;
    }

    private function iso(mixed $completedAt): string
    {
        return $completedAt instanceof \DateTimeInterface
            ? CarbonImmutable::instance($completedAt)->toIso8601String()
            : CarbonImmutable::parse((string) $completedAt)->toIso8601String();
    }
}
