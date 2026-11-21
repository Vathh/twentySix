<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingEventAttemptStatus;
use App\Domain\Training\TrainingEventWindow;
use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingKind;
use App\Domain\Training\TrainingPlanSessionItems;
use App\Domain\Training\TrainingSessionStats;
use App\Models\Training\TrainingDefinition;
use App\Models\Training\TrainingEventAttempt;
use App\Models\Training\TrainingEventDisqualification;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use App\Repositories\Training\TrainingDefinitionRepository;
use App\Repositories\Training\TrainingEventAttemptRepository;
use App\Repositories\Training\TrainingSessionRepository;
use App\Support\Http\DomainExceptionHttp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Start / resume / complete / abort prób eventu.
 * Crash i utrata sieci nie voidują — klient wznawia tę samą próbę.
 */
class TrainingEventAttemptService
{
    public function __construct(
        private TrainingDefinitionRepository $definitions,
        private TrainingEventAttemptRepository $attempts,
        private TrainingSessionRepository $sessions,
        private TrainingEventLifecycleService $lifecycle,
    ) {}

    /**
     * @return array{attempt: array<string, mixed>, event: array<string, mixed>}
     */
    public function start(User $user, int $definitionId, string $clientAttemptId): array
    {
        return DB::transaction(function () use ($user, $definitionId, $clientAttemptId): array {
            $definition = $this->definitions->lock($definitionId);
            $this->assertPlayableEvent($definition);

            $existingClient = $this->attempts->findByClient($user->id, $clientAttemptId);
            if ($existingClient !== null) {
                if ($existingClient->status === TrainingEventAttemptStatus::InProgress) {
                    return $this->payload($definition, $this->refreshExpiry($existingClient));
                }
                throw new TrainingException(
                    'Ta próba już się zakończyła.',
                    'training_event_attempt_closed',
                );
            }

            $open = $this->attempts->findOpenForUser($user->id, $definitionId);
            if ($open !== null) {
                $open = $this->expireIfNeeded($open);
                if ($open->status === TrainingEventAttemptStatus::InProgress) {
                    throw new TrainingException(
                        'Masz już otwartą próbę tego eventu. Wznów ją.',
                        'training_event_attempt_open',
                        DomainExceptionHttp::CONFLICT,
                    );
                }
            }

            $now = CarbonImmutable::now();
            $this->assertWithinSchedule($definition, $now);
            if ($definition->status === TrainingDefinitionStatus::Paused) {
                throw new TrainingException(
                    'Event jest wstrzymany. Nie zaczynaj nowych prób.',
                    'training_event_paused',
                    DomainExceptionHttp::CONFLICT,
                );
            }
            if ($definition->status === TrainingDefinitionStatus::Closing) {
                throw new TrainingException(
                    'Event się kończy. Nie zaczynaj nowych prób.',
                    'training_event_closing',
                    DomainExceptionHttp::CONFLICT,
                );
            }

            $windowKey = $this->windowKey($definition, $now);
            $used = $this->attempts->countConsumedInWindow($user->id, $definitionId, $windowKey);
            $limit = (int) ($definition->attempts_per_window ?? 1);
            if ($used >= $limit) {
                throw new TrainingException(
                    'Wykorzystałeś już próby w tym oknie.',
                    'training_event_window_exhausted',
                    DomainExceptionHttp::CONFLICT,
                );
            }

            $hours = (int) ($definition->attempt_timeout_hours ?: TrainingEventWindow::DEFAULT_TIMEOUT_HOURS);
            $attempt = $this->attempts->createInProgress(
                $user->id,
                $definitionId,
                $clientAttemptId,
                $windowKey,
                $now,
                $now->addHours(max(1, $hours)),
            );

            return $this->payload($definition, $attempt);
        });
    }

    /**
     * @return array{attempt: array<string, mixed>, event: array<string, mixed>}
     */
    public function current(User $user, int $definitionId): array
    {
        $definition = $this->definitions->find($definitionId);
        $this->assertEventKind($definition);
        $open = $this->attempts->findOpenForUser($user->id, $definitionId);
        if ($open === null) {
            throw new TrainingException(
                'Brak otwartej próby.',
                'training_event_attempt_not_found',
                DomainExceptionHttp::NOT_FOUND,
            );
        }
        $open = $this->expireIfNeeded($open);
        if ($open->status !== TrainingEventAttemptStatus::InProgress) {
            throw new TrainingException(
                'Otwarta próba wygasła.',
                'training_event_attempt_expired',
                DomainExceptionHttp::CONFLICT,
            );
        }

        return $this->payload($definition, $open);
    }

    /**
     * @param  array<mixed>  $payload
     * @return array{attempt: array<string, mixed>, sessionId: int}
     */
    public function complete(User $user, int $definitionId, array $payload): array
    {
        return DB::transaction(function () use ($user, $definitionId, $payload): array {
            $definition = $this->definitions->lock($definitionId);
            $this->assertEventKind($definition);

            $clientAttemptId = (string) ($payload['clientAttemptId'] ?? '');
            $attempt = $this->attempts->findByClient($user->id, $clientAttemptId);
            if ($attempt === null || (int) $attempt->definition_id !== $definitionId) {
                throw new TrainingException(
                    'Nie ma takiej próby.',
                    'training_event_attempt_not_found',
                    DomainExceptionHttp::NOT_FOUND,
                );
            }
            if ($attempt->status === TrainingEventAttemptStatus::Completed) {
                return [
                    'attempt' => $this->attemptCard($attempt),
                    'sessionId' => (int) ($this->sessions->findForUser($user->id, $clientAttemptId)?->id ?? 0),
                ];
            }
            $attempt = $this->expireIfNeeded($attempt);
            if ($attempt->status !== TrainingEventAttemptStatus::InProgress) {
                throw new TrainingException(
                    'Tej próby nie da się już ukończyć.',
                    'training_event_attempt_closed',
                );
            }

            $score = $this->score($payload['score'] ?? null);
            $stats = TrainingSessionStats::assertAggregate($payload['stats'] ?? null);
            if (array_key_exists('items', $payload)) {
                $stats['items'] = TrainingPlanSessionItems::assert($payload['items']);
            }
            $completedAt = CarbonImmutable::parse((string) ($payload['completedAt'] ?? now()->toIso8601String()));

            $this->attempts->markCompleted($attempt, $score ?? 0, $stats, $completedAt);

            $session = $this->sessions->findForUser($user->id, $clientAttemptId);
            if ($session === null) {
                $session = $this->sessions->create(
                    $user->id,
                    $clientAttemptId,
                    TrainingKind::Event,
                    $definitionId,
                    null,
                    $completedAt,
                    $score,
                    $stats,
                );
            }

            $this->lifecycle->maybeFinish($definition);

            return [
                'attempt' => $this->attemptCard($attempt->fresh()),
                'sessionId' => (int) $session->id,
            ];
        });
    }

    /**
     * @return array{attempt: array<string, mixed>}
     */
    public function abort(User $user, int $definitionId, string $clientAttemptId): array
    {
        return DB::transaction(function () use ($user, $definitionId, $clientAttemptId): array {
            $definition = $this->definitions->lock($definitionId);
            $this->assertEventKind($definition);
            $attempt = $this->attempts->findByClient($user->id, $clientAttemptId);
            if ($attempt === null || (int) $attempt->definition_id !== $definitionId) {
                throw new TrainingException(
                    'Nie ma takiej próby.',
                    'training_event_attempt_not_found',
                    DomainExceptionHttp::NOT_FOUND,
                );
            }
            if ($attempt->status === TrainingEventAttemptStatus::Voided) {
                return ['attempt' => $this->attemptCard($attempt)];
            }
            if ($attempt->status !== TrainingEventAttemptStatus::InProgress) {
                throw new TrainingException(
                    'Tej próby nie da się przerwać.',
                    'training_event_attempt_closed',
                );
            }
            $this->attempts->markVoided($attempt, 'abort', CarbonImmutable::now());
            $this->lifecycle->maybeFinish($definition);

            return ['attempt' => $this->attemptCard($attempt->fresh())];
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, mine: array<string, mixed>|null}
     */
    public function classification(User $user, int $definitionId): array
    {
        $definition = $this->definitions->find($definitionId);
        $this->assertEventKind($definition);
        if (! in_array($definition->status, [
            TrainingDefinitionStatus::Finished,
            TrainingDefinitionStatus::Closing,
            TrainingDefinitionStatus::Active,
            TrainingDefinitionStatus::Paused,
        ], true)) {
            throw new TrainingException(
                'Klasyfikacja niedostępna.',
                'training_event_classification_unavailable',
            );
        }

        $dqIds = TrainingEventDisqualification::query()
            ->where('definition_id', $definitionId)
            ->whereNull('reinstated_at')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $dqSet = array_fill_keys($dqIds, true);

        $best = [];
        foreach ($this->attempts->completedForEvent($definitionId) as $attempt) {
            $uid = (int) $attempt->user_id;
            if (isset($dqSet[$uid])) {
                continue;
            }
            if (! isset($best[$uid]) || (int) $attempt->score > (int) $best[$uid]->score) {
                $best[$uid] = $attempt;
            }
        }

        usort($best, function (TrainingEventAttempt $a, TrainingEventAttempt $b): int {
            if ((int) $a->score !== (int) $b->score) {
                return (int) $b->score <=> (int) $a->score;
            }
            $nameA = mb_strtolower((string) ($a->user?->player?->name ?? $a->user?->name ?? ''));
            $nameB = mb_strtolower((string) ($b->user?->player?->name ?? $b->user?->name ?? ''));

            return $nameA <=> $nameB;
        });

        $rows = [];
        $place = 0;
        $index = 0;
        $prev = null;
        $mine = null;
        foreach (array_values($best) as $attempt) {
            $index++;
            $score = (int) $attempt->score;
            if ($prev === null || $score !== $prev) {
                $place = $index;
                $prev = $score;
            }
            $player = $attempt->user?->player;
            $row = [
                'place' => $place,
                'userId' => (int) $attempt->user_id,
                'name' => (string) ($player?->name ?? $attempt->user?->name ?? 'Gracz'),
                'avatarUrl' => $player?->avatarUrl(),
                'score' => $score,
                'medal' => $this->medalForPlace($place, $definition->status === TrainingDefinitionStatus::Finished),
                'completedAt' => $attempt->completed_at?->toIso8601String(),
            ];
            $rows[] = $row;
            if ((int) $attempt->user_id === (int) $user->id) {
                $mine = $row;
            }
        }

        return ['items' => $rows, 'mine' => $mine];
    }

    private function expireIfNeeded(TrainingEventAttempt $attempt): TrainingEventAttempt
    {
        if ($attempt->status !== TrainingEventAttemptStatus::InProgress) {
            return $attempt;
        }
        if ($attempt->expires_at !== null && CarbonImmutable::now()->greaterThan($attempt->expires_at)) {
            return $this->attempts->markVoided($attempt, 'timeout', CarbonImmutable::now());
        }

        return $attempt;
    }

    private function refreshExpiry(TrainingEventAttempt $attempt): TrainingEventAttempt
    {
        return $this->expireIfNeeded($attempt);
    }

    private function assertPlayableEvent(TrainingDefinition $definition): void
    {
        $this->assertEventKind($definition);
        if (! in_array($definition->status, [
            TrainingDefinitionStatus::Active,
            TrainingDefinitionStatus::Paused,
            TrainingDefinitionStatus::Closing,
        ], true)) {
            throw new TrainingException(
                'Event nie jest dostępny.',
                'training_definition_not_active',
                DomainExceptionHttp::CONFLICT,
            );
        }
    }

    private function assertEventKind(TrainingDefinition $definition): void
    {
        if ($definition->kind !== TrainingKind::Event) {
            throw new TrainingException('To nie jest event.', 'training_definition_invalid');
        }
    }

    private function assertWithinSchedule(TrainingDefinition $definition, CarbonImmutable $now): void
    {
        if ($definition->starts_at !== null && $now->lt(CarbonImmutable::parse($definition->starts_at))) {
            throw new TrainingException(
                'Event jeszcze się nie zaczął.',
                'training_event_not_started',
                DomainExceptionHttp::CONFLICT,
            );
        }
        if ($definition->ends_at !== null && $now->gt(CarbonImmutable::parse($definition->ends_at))) {
            throw new TrainingException(
                'Event już się skończył.',
                'training_event_ended',
                DomainExceptionHttp::CONFLICT,
            );
        }
    }

    private function windowKey(TrainingDefinition $definition, CarbonImmutable $now): string
    {
        $mode = (string) ($definition->window_mode ?: TrainingEventWindow::MODE_CALENDAR_WEEK);
        $starts = CarbonImmutable::parse($definition->starts_at ?? $now);
        $anchor = $definition->window_anchor_date
            ? CarbonImmutable::parse($definition->window_anchor_date)
            : null;

        return TrainingEventWindow::key($mode, $now, $starts, $anchor);
    }

    /**
     * @return array{attempt: array<string, mixed>, event: array<string, mixed>}
     */
    private function payload(TrainingDefinition $definition, TrainingEventAttempt $attempt): array
    {
        return [
            'attempt' => $this->attemptCard($attempt),
            'event' => [
                'id' => (int) $definition->id,
                'name' => $definition->name,
                'status' => $definition->status->value,
                'endsAt' => $definition->ends_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptCard(TrainingEventAttempt $attempt): array
    {
        return [
            'id' => (int) $attempt->id,
            'clientAttemptId' => $attempt->client_attempt_id,
            'status' => $attempt->status->value,
            'windowKey' => $attempt->window_key,
            'startedAt' => $attempt->started_at?->toIso8601String(),
            'expiresAt' => $attempt->expires_at?->toIso8601String(),
            'score' => $attempt->score,
            'voidReason' => $attempt->void_reason,
        ];
    }

    private function score(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new TrainingException('Wynik musi być liczbą.', 'training_session_invalid');
    }

    private function medalForPlace(int $place, bool $finished): ?string
    {
        if (! $finished) {
            return null;
        }

        return match ($place) {
            1 => 'gold',
            2 => 'silver',
            3 => 'bronze',
            default => null,
        };
    }
}
