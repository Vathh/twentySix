<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingBadgeThresholds;
use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingEventAttemptStatus;
use App\Domain\Training\TrainingGoalPaths;
use App\Domain\Training\TrainingKind;
use App\Domain\Training\TrainingSkillBadgeThresholds;
use App\Models\Training\TrainingDefinition;
use App\Models\Training\TrainingEventAttempt;
use App\Models\Training\TrainingEventDisqualification;
use App\Models\Users\User;

/**
 * Cele i odznaki treningowe (numer 13) + medale eventów.
 */
class TrainingGoalsService
{
    public function __construct(
        private TrainingProgressAggregate $aggregate,
    ) {}

    /**
     * @return array{goals: list<array<string, mixed>>, badges: list<array<string, mixed>>, medals: list<array<string, mixed>>}
     */
    public function forUser(User $user): array
    {
        $agg = $this->aggregate->forUser($user);

        return [
            'goals' => $this->goals($agg),
            'badges' => $this->badges($user, $agg),
            'medals' => $this->medalsFor($user),
        ];
    }

    /**
     * @deprecated Użyj forUser — zostawione pod stare wywołania w testach.
     *
     * @return array{goals: list<array<string, mixed>>, badges: list<array<string, mixed>>, medals: list<array<string, mixed>>}
     */
    public function dryProgress(User $user): array
    {
        return $this->forUser($user);
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return list<array<string, mixed>>
     */
    private function goals(array $agg): array
    {
        $values = [
            'scoring' => $this->scoringAverage($agg),
            'doubles' => $this->percent($agg['doublesHits'], $agg['doublesAttempts']),
            'checkouts' => $this->percent($agg['checkoutsClosed'], $agg['checkoutAttempts']),
            'regularity' => (float) $agg['regularitySessions'],
            'challenges' => $agg['challengeBest'] !== null ? (float) $agg['challengeBest'] : 0.0,
        ];

        $out = [];
        foreach (TrainingGoalPaths::all() as $path) {
            $id = $path['id'];
            $value = $values[$id] ?? 0.0;
            $thresholds = $path['thresholds'];
            $current = null;
            $completedCount = 0;
            foreach ($thresholds as $threshold) {
                if ($value + 1e-9 >= (float) $threshold) {
                    $completedCount++;

                    continue;
                }
                $current = $threshold;
                break;
            }
            if ($current === null) {
                $current = $thresholds[array_key_last($thresholds)];
            }
            $prev = $completedCount > 0 ? (float) $thresholds[$completedCount - 1] : 0.0;
            $span = max(0.0001, (float) $current - $prev);
            $progress = min(1.0, max(0.0, ($value - $prev) / $span));
            $allDone = $completedCount >= count($thresholds);

            $out[] = [
                'id' => $id,
                'name' => $path['name'],
                'unit' => $path['unit'],
                'rule' => $path['rule'],
                'threshold' => $allDone ? $thresholds[array_key_last($thresholds)] : $current,
                'value' => $this->roundValue($path['unit'], $value),
                'progress' => round($progress, 4),
                'completed' => $allDone,
                'completedThresholds' => $completedCount,
                'attempts' => match ($id) {
                    'scoring' => $agg['scoringApproaches'],
                    'doubles' => $agg['doublesAttempts'],
                    'checkouts' => $agg['checkoutAttempts'],
                    default => null,
                },
                'assessmentReady' => match ($id) {
                    'doubles', 'checkouts' => $agg[$id === 'doubles' ? 'doublesAttempts' : 'checkoutAttempts'] >= 20,
                    'scoring' => $agg['scoringApproaches'] >= 20,
                    default => true,
                },
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return list<array<string, mixed>>
     */
    private function badges(User $user, array $agg): array
    {
        $scoring = $this->scoringBadge($agg);
        $doubles = $this->doublesBadge($agg);
        $checkouts = $this->checkoutsBadge($agg);
        $challenges = $this->challengeBadge($user, $agg);

        return [$scoring, $doubles, $checkouts, $challenges];
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return array<string, mixed>
     */
    private function scoringBadge(array $agg): array
    {
        $approaches = $agg['scoringApproaches'];
        $average = $this->scoringAverage($agg);
        $share = $approaches > 0 ? $agg['scoringVisitsAtLeast60'] / $approaches : 0.0;
        $level = null;
        if ($approaches >= 20) {
            foreach (TrainingSkillBadgeThresholds::scoring() as $row) {
                if ($average + 1e-9 >= $row['average'] && $share + 1e-9 >= $row['shareAtLeast60']) {
                    $level = $row['level'];
                }
            }
        }

        return $this->badgeCard('scoring', 'Scoring', $level, $this->nextProgress(
            TrainingSkillBadgeThresholds::scoring(),
            $level,
            static fn (array $row): float => min(
                $average / max(0.0001, $row['average']),
                $share / max(0.0001, $row['shareAtLeast60']),
            ),
        ), placeholder: $level === null);
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return array<string, mixed>
     */
    private function doublesBadge(array $agg): array
    {
        $attempts = $agg['doublesAttempts'];
        $hits = $agg['doublesHits'];
        $percent = $this->percent($hits, $attempts);
        $level = null;
        if ($attempts >= 20) {
            foreach (TrainingSkillBadgeThresholds::doubles() as $row) {
                if ($percent + 1e-9 >= $row['percent'] && $hits >= $row['hits']) {
                    $level = $row['level'];
                }
            }
        }

        return $this->badgeCard('doubles', 'Doubles', $level, $this->nextProgress(
            TrainingSkillBadgeThresholds::doubles(),
            $level,
            static fn (array $row): float => min(
                $percent / max(0.0001, $row['percent']),
                $hits / max(1, $row['hits']),
            ),
        ), placeholder: $level === null);
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return array<string, mixed>
     */
    private function checkoutsBadge(array $agg): array
    {
        $attempts = $agg['checkoutAttempts'];
        $closed = $agg['checkoutsClosed'];
        $percent = $this->percent($closed, $attempts);
        $level = null;
        if ($attempts >= 20) {
            foreach (TrainingSkillBadgeThresholds::checkouts() as $row) {
                if ($percent + 1e-9 >= $row['percent'] && $closed >= $row['closed']) {
                    $level = $row['level'];
                }
            }
        }

        return $this->badgeCard('checkouts', 'Checkouty', $level, $this->nextProgress(
            TrainingSkillBadgeThresholds::checkouts(),
            $level,
            static fn (array $row): float => min(
                $percent / max(0.0001, $row['percent']),
                $closed / max(1, $row['closed']),
            ),
        ), placeholder: $level === null);
    }

    /**
     * @param  array<string, mixed>  $agg
     * @return array<string, mixed>
     */
    private function challengeBadge(User $user, array $agg): array
    {
        $scores = $agg['challengeRecentScores'];
        $definition = TrainingDefinition::query()
            ->where('kind', TrainingKind::Challenge)
            ->whereNotNull('badge_thresholds')
            ->whereIn('status', [
                TrainingDefinitionStatus::Active->value,
                TrainingDefinitionStatus::Inactive->value,
            ])
            ->orderByDesc('id')
            ->first();

        $thresholds = is_array($definition?->badge_thresholds) ? $definition->badge_thresholds : null;
        $level = null;
        $progressToNext = 0.0;
        $nextLevel = 'iron';
        if (count($scores) >= 5 && is_array($thresholds)) {
            $avg = array_sum($scores) / 5.0;
            foreach (TrainingBadgeThresholds::levels() as $lvl) {
                $need = $thresholds[$lvl] ?? null;
                if (is_int($need) && $avg + 1e-9 >= $need) {
                    $level = $lvl;
                }
            }
            $levels = TrainingBadgeThresholds::levels();
            $idx = $level === null ? -1 : array_search($level, $levels, true);
            $next = $levels[is_int($idx) ? $idx + 1 : 0] ?? null;
            if (is_string($next) && isset($thresholds[$next])) {
                $nextLevel = $next;
                $need = (float) $thresholds[$next];
                $prevNeed = $level !== null ? (float) ($thresholds[$level] ?? 0) : 0.0;
                $span = max(0.0001, $need - $prevNeed);
                $progressToNext = min(1.0, max(0.0, ($avg - $prevNeed) / $span));
            } else {
                $nextLevel = $level ?? 'diamond';
                $progressToNext = 1.0;
            }
        }

        unset($user);

        return $this->badgeCard('challenges', 'Wyzwania', $level, [
            'progressToNext' => round($progressToNext, 4),
            'nextLevel' => $nextLevel,
            'nextLevelLabel' => TrainingBadgeThresholds::labels()[$nextLevel] ?? $nextLevel,
        ], placeholder: count($scores) < 5);
    }

    /**
     * @param  array{progressToNext: float, nextLevel: string, nextLevelLabel: string}  $next
     * @return array<string, mixed>
     */
    private function badgeCard(string $id, string $name, ?string $level, array $next, bool $placeholder = false): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'level' => $level,
            'levelLabel' => $level !== null ? (TrainingBadgeThresholds::labels()[$level] ?? $level) : null,
            'progressToNext' => $next['progressToNext'],
            'nextLevel' => $next['nextLevel'],
            'nextLevelLabel' => $next['nextLevelLabel'],
            'placeholder' => $placeholder && $level === null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): float  $ratio
     * @return array{progressToNext: float, nextLevel: string, nextLevelLabel: string}
     */
    private function nextProgress(array $rows, ?string $currentLevel, callable $ratio): array
    {
        $levels = array_column($rows, 'level');
        $idx = $currentLevel === null ? -1 : array_search($currentLevel, $levels, true);
        $nextIdx = is_int($idx) ? $idx + 1 : 0;
        if (! isset($rows[$nextIdx])) {
            $last = $rows[array_key_last($rows)];

            return [
                'progressToNext' => 1.0,
                'nextLevel' => $last['level'],
                'nextLevelLabel' => TrainingBadgeThresholds::labels()[$last['level']] ?? $last['level'],
            ];
        }
        $next = $rows[$nextIdx];

        return [
            'progressToNext' => round(min(1.0, max(0.0, $ratio($next))), 4),
            'nextLevel' => $next['level'],
            'nextLevelLabel' => TrainingBadgeThresholds::labels()[$next['level']] ?? $next['level'],
        ];
    }

    /**
     * @param  array<string, mixed>  $agg
     */
    private function scoringAverage(array $agg): float
    {
        if ($agg['scoringApproaches'] <= 0) {
            return 0.0;
        }

        return round($agg['scoringTotal'] / $agg['scoringApproaches'], 2);
    }

    private function percent(int $hits, int $attempts): float
    {
        if ($attempts <= 0) {
            return 0.0;
        }

        return round(($hits * 100.0) / $attempts, 2);
    }

    private function roundValue(string $unit, float $value): float|int
    {
        return match ($unit) {
            'sessions', 'score' => (int) round($value),
            default => round($value, 2),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function medalsFor(User $user): array
    {
        $finished = TrainingDefinition::query()
            ->where('kind', TrainingKind::Event)
            ->where('status', TrainingDefinitionStatus::Finished)
            ->orderByDesc('finished_at')
            ->get();

        $medals = [];
        foreach ($finished as $event) {
            $dq = TrainingEventDisqualification::query()
                ->where('definition_id', $event->id)
                ->where('user_id', $user->id)
                ->whereNull('reinstated_at')
                ->exists();
            if ($dq) {
                continue;
            }

            $mine = TrainingEventAttempt::query()
                ->where('definition_id', $event->id)
                ->where('user_id', $user->id)
                ->where('status', TrainingEventAttemptStatus::Completed)
                ->orderByDesc('score')
                ->first();
            if ($mine === null) {
                continue;
            }

            $dqIds = TrainingEventDisqualification::query()
                ->where('definition_id', $event->id)
                ->whereNull('reinstated_at')
                ->pluck('user_id')
                ->all();

            $better = TrainingEventAttempt::query()
                ->where('definition_id', $event->id)
                ->where('status', TrainingEventAttemptStatus::Completed)
                ->where('score', '>', $mine->score)
                ->when($dqIds !== [], fn ($q) => $q->whereNotIn('user_id', $dqIds))
                ->pluck('user_id')
                ->unique()
                ->count();

            $place = $better + 1;
            $color = match ($place) {
                1 => 'gold',
                2 => 'silver',
                3 => 'bronze',
                default => null,
            };
            if ($color === null) {
                continue;
            }

            $medals[] = [
                'id' => 'event_'.$event->id,
                'name' => 'Medal',
                'placeholder' => false,
                'eventName' => $event->name,
                'color' => $color,
                'place' => $place,
                'awardedAt' => $event->finished_at?->toIso8601String(),
            ];
        }

        if ($medals === []) {
            return [[
                'id' => 'medal_slot',
                'name' => 'Medal',
                'placeholder' => true,
                'eventName' => null,
                'color' => null,
                'awardedAt' => null,
            ]];
        }

        return $medals;
    }
}
