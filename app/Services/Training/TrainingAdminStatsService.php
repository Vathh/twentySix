<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingDefinition;
use App\Repositories\Training\TrainingAdminStatsRepository;
use Carbon\CarbonImmutable;

class TrainingAdminStatsService
{
    public function __construct(
        private TrainingAdminStatsRepository $stats,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function forDefinition(TrainingDefinition $definition, array $query = []): ?array
    {
        return match ($definition->kind) {
            TrainingKind::Plan, TrainingKind::Challenge => $this->sessionBased($definition, $query),
            TrainingKind::Event => $this->eventBased($definition, $query),
            TrainingKind::Exercise => null,
        };
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function sessionBased(TrainingDefinition $definition, array $query): array
    {
        $from = $this->date($query['from'] ?? null, CarbonImmutable::now()->subDays(29)->startOfDay());
        $to = $this->date($query['to'] ?? null, CarbonImmutable::now()->endOfDay());
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        $totals = $this->stats->sessionTotals((int) $definition->id);
        $payload = [
            'kind' => $definition->kind->value,
            'totals' => $totals,
            'users' => $this->stats->sessionUserCounts(
                (int) $definition->id,
                isset($query['minCompletions']) && is_numeric($query['minCompletions'])
                    ? (int) $query['minCompletions']
                    : null,
                is_string($query['sort'] ?? null) ? (string) $query['sort'] : 'completions',
                is_string($query['dir'] ?? null) ? (string) $query['dir'] : 'desc',
            )->map(fn ($row) => [
                'userId' => (int) $row->user_id,
                'name' => $row->name ?: $row->email,
                'completions' => (int) $row->completions,
            ])->all(),
            'daily' => $this->stats->sessionDaily((int) $definition->id, $from, $to),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];

        if ($definition->kind === TrainingKind::Challenge) {
            $attempts = $this->stats->challengeAttempts(
                (int) $definition->id,
                isset($query['userId']) && is_numeric($query['userId']) ? (int) $query['userId'] : null,
                $from,
                $to,
                is_string($query['attemptSort'] ?? null) ? (string) $query['attemptSort'] : 'date',
                is_string($query['attemptDir'] ?? null) ? (string) $query['attemptDir'] : 'desc',
            );
            $payload['attempts'] = $attempts->map(fn ($session) => [
                'id' => (int) $session->id,
                'userId' => (int) $session->user_id,
                'name' => $session->user?->player?->name ?? $session->user?->email ?? '—',
                'score' => $session->score,
                'completedAt' => $session->completed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'stats' => $session->stats,
            ])->all();
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function eventBased(TrainingDefinition $definition, array $query): array
    {
        $from = $this->date($query['from'] ?? null, CarbonImmutable::now()->subDays(29)->startOfDay());
        $to = $this->date($query['to'] ?? null, CarbonImmutable::now()->endOfDay());
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        $attempts = $this->stats->eventCompletedAttempts((int) $definition->id);

        return [
            'kind' => 'event',
            'totals' => $this->stats->eventAttemptTotals((int) $definition->id),
            'participants' => $attempts->map(fn ($attempt) => [
                'userId' => (int) $attempt->user_id,
                'name' => $attempt->user?->player?->name ?? $attempt->user?->email ?? '—',
                'score' => $attempt->score,
                'completedAt' => $attempt->completed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'stats' => $attempt->stats,
            ])->all(),
            'daily' => $this->stats->eventDailyCompleted((int) $definition->id, $from, $to),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    private function date(mixed $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
