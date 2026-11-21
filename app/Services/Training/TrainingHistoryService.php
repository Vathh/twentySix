<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingKind;
use App\Models\Users\User;
use App\Repositories\Training\TrainingSessionRepository;
use App\Support\Http\DomainExceptionHttp;
use Carbon\CarbonImmutable;

class TrainingHistoryService
{
    private const PAGE_SIZE = 5;

    private const ALLOWED_DAYS = [7, 30, 90];

    public function __construct(
        private TrainingSessionRepository $sessions,
    ) {}

    /**
     * @param  array<mixed>  $query
     * @return array{items: list<array<string, mixed>>, hasMore: bool}
     */
    public function list(User $user, array $query): array
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
            self::PAGE_SIZE + 1,
        );

        $hasMore = $rows->count() > self::PAGE_SIZE;
        $page = $rows->take(self::PAGE_SIZE)->values();

        return [
            'items' => $page->map(fn ($session) => TrainingHistoryCard::from($session))->all(),
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $user, int $id): array
    {
        $session = $this->sessions->findOwned((int) $user->id, $id);
        if ($session === null) {
            throw new TrainingException(
                'Nie ma takiej sesji.',
                'training_session_not_found',
                DomainExceptionHttp::NOT_FOUND,
            );
        }

        return TrainingHistoryCard::from($session, true);
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
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }
            $kind = TrainingKind::tryFrom(trim((string) $item));
            if ($kind === null) {
                throw new TrainingException(
                    'Nieznany rodzaj sesji.',
                    'training_history_invalid',
                );
            }
            // Wolna gra jest lokalna — API jej nie filtruje jako kind.
            $kinds[$kind->value] = $kind;
        }

        return array_values($kinds);
    }

    private function since(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $days = is_int($value) ? $value : (int) $value;
        if (! in_array($days, self::ALLOWED_DAYS, true)) {
            throw new TrainingException(
                'Zakres dat to 7, 30 albo 90 dni.',
                'training_history_invalid',
            );
        }

        return CarbonImmutable::now()->subDays($days)->startOfDay();
    }
}
