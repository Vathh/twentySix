<?php

namespace App\Services\Training;

use App\Domain\Career\CareerSnapshotMetrics;
use App\Domain\Training\TrainingKind;
use App\Domain\Training\TrainingMetricExtractor;
use App\Domain\Training\TrainingSkillWindow;
use App\Enums\CareerSource;
use App\Models\Career\PlayerGameSnapshot;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use App\Repositories\Career\PlayerGameSnapshotRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Mix skill-statystyk: sesje katalogowe + wolna gra JA (source=training).
 */
class TrainingSkillStatsService
{
    public function __construct(
        private PlayerGameSnapshotRepository $snapshots,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function skillSeries(User $user, string $windowKey, int $offset): array
    {
        try {
            $window = TrainingSkillWindow::fromQuery($windowKey, $offset);
        } catch (InvalidArgumentException) {
            abort(422, 'Nieznane okno skill-statystyk.');
        }

        $playerId = $user->player?->id;
        $events = $this->timelineEvents($user, $playerId, $window->startUtc(), $window->endExclusiveUtc());
        $earliest = $this->earliestAt($user, $playerId);

        $cumDarts = 0;
        $cumPoints = 0;
        $cumAttempts = 0;
        $cumSuccesses = 0;
        $x01Series = [];
        $doubleSeries = [];
        $hasX01 = false;
        $hasDoubles = false;

        foreach ($events as $event) {
            if ($event['darts'] > 0) {
                $hasX01 = true;
                $cumDarts += $event['darts'];
                $cumPoints += $event['points'];
                $x01Series[] = [
                    't' => $event['date'],
                    'value' => round(($cumPoints / $cumDarts) * 3, 2),
                    'games' => count($x01Series) + 1,
                ];
            }
            if ($event['doubleTracked']) {
                $hasDoubles = true;
                $cumAttempts += $event['doubleAttempts'];
                $cumSuccesses += $event['doubleSuccesses'];
                if ($cumAttempts > 0) {
                    $doubleSeries[] = [
                        't' => $event['date'],
                        'value' => round(($cumSuccesses / $cumAttempts) * 100, 1),
                        'label' => $cumSuccesses.'/'.$cumAttempts,
                        'games' => count($doubleSeries) + 1,
                    ];
                }
            }
        }

        $x01Average = $hasX01 && $cumDarts > 0 ? round(($cumPoints / $cumDarts) * 3, 2) : null;
        $doublePct = $hasDoubles && $cumAttempts > 0
            ? round(($cumSuccesses / $cumAttempts) * 100, 1)
            : null;

        return [
            'window' => $window->key,
            'offset' => $window->offset,
            'range' => [
                'start' => $window->rangeStartDate(),
                'end' => $window->rangeEndDate(),
            ],
            'canGoBack' => $earliest !== null && $earliest->lt($window->startUtc()),
            'canGoForward' => $window->offset > 0,
            'hero' => [
                'x01Average' => $x01Average,
                'doublePct' => $doublePct,
                'doubleAttempts' => $hasDoubles ? $cumAttempts : null,
                'doubleSuccesses' => $hasDoubles ? $cumSuccesses : null,
                'doubleLabel' => $hasDoubles && $cumAttempts > 0 ? $cumSuccesses.'/'.$cumAttempts : null,
                'hasX01' => $hasX01,
                'hasDoubles' => $hasDoubles,
            ],
            'series' => [
                'x01_average' => $x01Series,
                'double_pct' => $doubleSeries,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sectorAims(User $user, int $offset): array
    {
        $window = TrainingSkillWindow::sector($offset);
        $sessions = $this->sessionsInRange($user, $window->startUtc(), $window->endExclusiveUtc());
        $byAim = [];

        foreach ($sessions as $session) {
            foreach ($this->aimsFromSession($session) as $row) {
                $aim = $row['aim'];
                if (! isset($byAim[$aim])) {
                    $byAim[$aim] = ['aim' => $aim, 'attempts' => 0, 'hits' => 0];
                }
                $byAim[$aim]['attempts'] += $row['attempts'];
                $byAim[$aim]['hits'] += $row['hits'];
            }
        }

        $aims = [];
        foreach ($byAim as $row) {
            $attempts = $row['attempts'];
            $hits = $row['hits'];
            $aims[] = [
                'aim' => $row['aim'],
                'attempts' => $attempts,
                'hits' => $hits,
                'percent' => $attempts > 0 ? round(($hits * 1000) / $attempts) / 10 : 0.0,
            ];
        }

        usort($aims, static function (array $a, array $b): int {
            $pctCmp = ($b['percent'] <=> $a['percent']);
            if ($pctCmp !== 0) {
                return $pctCmp;
            }

            return strcmp($a['aim'], $b['aim']);
        });

        $earliest = $this->earliestSessionAt($user);

        return [
            'window' => '30d',
            'offset' => $window->offset,
            'range' => [
                'start' => $window->rangeStartDate(),
                'end' => $window->rangeEndDate(),
            ],
            'canGoBack' => $earliest !== null && $earliest->lt($window->startUtc()),
            'canGoForward' => $window->offset > 0,
            'aims' => array_values($aims),
        ];
    }

    /**
     * @return list<array{
     *   at: CarbonImmutable,
     *   date: string,
     *   darts: int,
     *   points: int,
     *   doubleTracked: bool,
     *   doubleAttempts: int,
     *   doubleSuccesses: int
     * }>
     */
    private function timelineEvents(
        User $user,
        ?int $playerId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $toExclusiveUtc,
    ): array {
        $events = [];

        if ($playerId !== null) {
            /** @var Collection<int, PlayerGameSnapshot> $rows */
            $rows = $this->snapshots->listForPlayer(
                $playerId,
                [CareerSource::Training],
                $fromUtc,
                $toExclusiveUtc,
            );
            foreach ($rows as $snapshot) {
                $m = is_array($snapshot->metrics) ? $snapshot->metrics : [];
                $darts = 0;
                $points = 0;
                if (CareerSnapshotMetrics::isX01((string) $snapshot->game_type)) {
                    $gameDarts = (int) ($m['darts_thrown'] ?? 0);
                    if ($gameDarts > 0) {
                        $darts = $gameDarts;
                        $points = (int) ($m['points'] ?? 0);
                    }
                }
                $tracked = ! empty($m['double_tracked']);
                $at = CarbonImmutable::parse($snapshot->occurred_at)->utc();
                $events[] = [
                    'at' => $at,
                    'date' => $at->timezone(TrainingSkillWindow::TIMEZONE)->toDateString(),
                    'darts' => $darts,
                    'points' => $points,
                    'doubleTracked' => $tracked,
                    'doubleAttempts' => $tracked ? (int) ($m['double_attempts'] ?? 0) : 0,
                    'doubleSuccesses' => $tracked ? (int) ($m['double_successes'] ?? 0) : 0,
                ];
            }
        }

        foreach ($this->sessionsInRange($user, $fromUtc, $toExclusiveUtc) as $session) {
            $metrics = $this->metricsFromSession($session);
            if ($metrics['darts'] <= 0 && ! $metrics['doubleTracked']) {
                continue;
            }
            $at = CarbonImmutable::parse($session->completed_at)->utc();
            $events[] = [
                'at' => $at,
                'date' => $at->timezone(TrainingSkillWindow::TIMEZONE)->toDateString(),
                'darts' => $metrics['darts'],
                'points' => $metrics['points'],
                'doubleTracked' => $metrics['doubleTracked'],
                'doubleAttempts' => $metrics['doubleAttempts'],
                'doubleSuccesses' => $metrics['doubleSuccesses'],
            ];
        }

        usort($events, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return $events;
    }

    /**
     * @return Collection<int, TrainingSession>
     */
    private function sessionsInRange(User $user, CarbonImmutable $fromUtc, CarbonImmutable $toExclusiveUtc): Collection
    {
        return TrainingSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $fromUtc)
            ->where('completed_at', '<', $toExclusiveUtc)
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{darts: int, points: int, doubleTracked: bool, doubleAttempts: int, doubleSuccesses: int}
     */
    private function metricsFromSession(TrainingSession $session): array
    {
        $darts = 0;
        $points = 0;
        $doubleAttempts = 0;
        $doubleSuccesses = 0;

        foreach ($this->exerciseParts($session) as $part) {
            $extracted = TrainingMetricExtractor::fromExercise($part['key'], $part['stats']);
            if ($extracted['scoringApproaches'] > 0) {
                // scoring_65: total = suma wizyt, approaches = wizyty → darts ≈ approaches×3
                $darts += $extracted['scoringApproaches'] * 3;
                $points += $extracted['scoringTotal'];
            }
            if ($extracted['doublesAttempts'] > 0) {
                $doubleAttempts += $extracted['doublesAttempts'];
                $doubleSuccesses += $extracted['doublesHits'];
            }
        }

        return [
            'darts' => $darts,
            'points' => $points,
            'doubleTracked' => $doubleAttempts > 0,
            'doubleAttempts' => $doubleAttempts,
            'doubleSuccesses' => $doubleSuccesses,
        ];
    }

    /**
     * @return list<array{key: string, stats: array<string, mixed>|null}>
     */
    private function exerciseParts(TrainingSession $session): array
    {
        if ($session->kind === TrainingKind::Exercise && is_string($session->exercise_key)) {
            return [[
                'key' => $session->exercise_key,
                'stats' => is_array($session->stats) ? $session->stats : null,
            ]];
        }

        if ($session->kind !== TrainingKind::Plan && $session->kind !== TrainingKind::Event) {
            return [];
        }

        $items = is_array($session->stats) ? ($session->stats['items'] ?? null) : null;
        if (! is_array($items)) {
            return [];
        }

        $parts = [];
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
            if (! is_string($key)) {
                continue;
            }
            $stats = is_array($item['stats'] ?? null) ? $item['stats'] : null;
            if ($stats === null && is_array($item['aims'] ?? null)) {
                $stats = ['aims' => $item['aims']];
            }
            $parts[] = ['key' => $key, 'stats' => $stats];
        }

        return $parts;
    }

    /**
     * @return list<array{aim: string, attempts: int, hits: int}>
     */
    private function aimsFromSession(TrainingSession $session): array
    {
        $rows = [];
        foreach ($this->exerciseParts($session) as $part) {
            $stats = $part['stats'];
            $aims = is_array($stats) ? ($stats['aims'] ?? null) : null;
            if (! is_array($aims)) {
                continue;
            }
            foreach ($aims as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $aim = $row['aim'] ?? null;
                if (! is_string($aim) || $aim === '') {
                    continue;
                }
                $attempts = max(0, (int) ($row['attempts'] ?? 0));
                $hits = max(0, (int) ($row['hits'] ?? 0));
                if ($attempts <= 0 && $hits <= 0) {
                    continue;
                }
                $rows[] = [
                    'aim' => $aim,
                    'attempts' => $attempts,
                    'hits' => min($hits, $attempts > 0 ? $attempts : $hits),
                ];
            }
        }

        return $rows;
    }

    private function earliestAt(User $user, ?int $playerId): ?CarbonImmutable
    {
        $sessionAt = $this->earliestSessionAt($user);
        $snapshotAt = null;
        if ($playerId !== null) {
            $first = PlayerGameSnapshot::query()
                ->where('player_id', $playerId)
                ->where('source', CareerSource::Training)
                ->orderBy('occurred_at')
                ->value('occurred_at');
            if ($first !== null) {
                $snapshotAt = CarbonImmutable::parse($first)->utc();
            }
        }

        if ($sessionAt === null) {
            return $snapshotAt;
        }
        if ($snapshotAt === null) {
            return $sessionAt;
        }

        return $sessionAt->lt($snapshotAt) ? $sessionAt : $snapshotAt;
    }

    private function earliestSessionAt(User $user): ?CarbonImmutable
    {
        $first = TrainingSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->value('completed_at');

        return $first !== null ? CarbonImmutable::parse($first)->utc() : null;
    }
}
