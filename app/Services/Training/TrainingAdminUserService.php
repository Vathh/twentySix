<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingKind;
use App\Models\Users\User;
use App\Repositories\Training\TrainingSessionRepository;
use Carbon\CarbonImmutable;

class TrainingAdminUserService
{
    public function __construct(
        private TrainingSessionRepository $sessions,
        private TrainingExerciseCountService $exerciseCount,
        private TrainingRecordsService $records,
        private TrainingMedalsService $medals,
    ) {}

    /**
     * @param  array{kinds?: mixed, days?: mixed, beforeCompletedAt?: mixed, beforeId?: mixed}  $query
     * @return array{
     *     completedExerciseCount: int,
     *     records: list<array<string, mixed>>,
     *     medals: list<array<string, mixed>>,
     *     history: array{items: list<array<string, mixed>>, hasMore: bool}
     * }
     */
    public function present(User $user, array $query = []): array
    {
        return [
            'completedExerciseCount' => $this->exerciseCount->forUser($user),
            'records' => $this->records->forUser($user),
            'medals' => $this->medals->forUser($user),
            'history' => $this->history($user, $query),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{items: list<array<string, mixed>>, hasMore: bool}
     */
    private function history(User $user, array $query): array
    {
        $kinds = $this->kinds($query['kinds'] ?? null);
        $since = $this->since($query['days'] ?? null);
        $beforeCompletedAt = null;
        $beforeId = null;
        if (isset($query['beforeCompletedAt'], $query['beforeId'])) {
            $beforeCompletedAt = CarbonImmutable::parse((string) $query['beforeCompletedAt']);
            $beforeId = (int) $query['beforeId'];
        }

        $rows = $this->sessions->historyPage(
            (int) $user->id,
            $kinds,
            $since,
            $beforeCompletedAt,
            $beforeId,
            21,
        );

        $hasMore = $rows->count() > 20;
        $page = $rows->take(20)->values();

        return [
            'items' => $page->map(fn ($session) => TrainingHistoryCard::from($session, true))->all(),
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @return list<TrainingKind>|null
     */
    private function kinds(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        $raw = is_array($value) ? $value : explode(',', (string) $value);
        $kinds = [];
        foreach ($raw as $item) {
            $kind = TrainingKind::tryFrom(trim((string) $item));
            if ($kind !== null) {
                $kinds[$kind->value] = $kind;
            }
        }

        return $kinds === [] ? null : array_values($kinds);
    }

    private function since(mixed $days): ?CarbonImmutable
    {
        $value = is_numeric($days) ? (int) $days : null;
        if ($value !== 7 && $value !== 30 && $value !== 90) {
            return null;
        }

        return CarbonImmutable::now()->subDays($value)->startOfDay();
    }
}
