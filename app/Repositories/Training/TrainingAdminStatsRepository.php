<?php

namespace App\Repositories\Training;

use App\Domain\Training\TrainingEventAttemptStatus;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingEventAttempt;
use App\Models\Training\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingAdminStatsRepository
{
    /**
     * @return array{completed: int, uniqueUsers: int}
     */
    public function sessionTotals(int $definitionId): array
    {
        $query = TrainingSession::query()->where('definition_id', $definitionId);

        return [
            'completed' => (clone $query)->count(),
            'uniqueUsers' => (int) (clone $query)->selectRaw('COUNT(DISTINCT user_id) as aggregate')->value('aggregate'),
        ];
    }

    /**
     * @return Collection<int, object{user_id: int, completions: int, name: string|null, email: string}>
     */
    public function sessionUserCounts(
        int $definitionId,
        ?int $minCompletions,
        string $sort,
        string $dir,
    ): Collection {
        $query = TrainingSession::query()
            ->from('training_sessions')
            ->join('users', 'users.id', '=', 'training_sessions.user_id')
            ->leftJoin('players', 'players.user_id', '=', 'users.id')
            ->whereNull('training_sessions.wipe_id')
            ->where('training_sessions.definition_id', $definitionId)
            ->groupBy('training_sessions.user_id', 'users.email', 'players.name')
            ->select([
                'training_sessions.user_id',
                DB::raw('COUNT(*) as completions'),
                'players.name',
                'users.email',
            ]);

        if ($minCompletions !== null && $minCompletions > 0) {
            $query->havingRaw('COUNT(*) >= ?', [$minCompletions]);
        }

        $direction = strtolower($dir) === 'asc' ? 'asc' : 'desc';
        if ($sort === 'name') {
            $query->orderByRaw('COALESCE(players.name, users.email) '.$direction);
        } else {
            $query->orderBy('completions', $direction)->orderByRaw('COALESCE(players.name, users.email) asc');
        }

        return $query->get();
    }

    /**
     * @return list<array{date: string, completed: int}>
     */
    public function sessionDaily(int $definitionId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = TrainingSession::query()
            ->where('definition_id', $definitionId)
            ->whereBetween('completed_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        return $this->fillDays($from, $to, $rows);
    }

    /**
     * @return Collection<int, TrainingSession>
     */
    public function challengeAttempts(
        int $definitionId,
        ?int $userId,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        string $sort,
        string $dir,
    ): Collection {
        $query = TrainingSession::query()
            ->with(['user.player'])
            ->where('definition_id', $definitionId)
            ->where('kind', TrainingKind::Challenge);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        if ($from !== null) {
            $query->where('completed_at', '>=', $from->startOfDay());
        }
        if ($to !== null) {
            $query->where('completed_at', '<=', $to->endOfDay());
        }

        $direction = strtolower($dir) === 'asc' ? 'asc' : 'desc';
        if ($sort === 'score') {
            $query->orderBy('score', $direction)->orderByDesc('completed_at');
        } else {
            $query->orderBy('completed_at', $direction)->orderByDesc('id');
        }

        return $query->limit(200)->get();
    }

    /**
     * @return array{inProgress: int, completed: int, voided: int, uniqueCompletedUsers: int}
     */
    public function eventAttemptTotals(int $definitionId): array
    {
        $base = TrainingEventAttempt::query()->where('definition_id', $definitionId);

        return [
            'inProgress' => (clone $base)->where('status', TrainingEventAttemptStatus::InProgress)->count(),
            'completed' => (clone $base)->where('status', TrainingEventAttemptStatus::Completed)->count(),
            'voided' => (clone $base)->where('status', TrainingEventAttemptStatus::Voided)->count(),
            'uniqueCompletedUsers' => (int) (clone $base)
                ->where('status', TrainingEventAttemptStatus::Completed)
                ->selectRaw('COUNT(DISTINCT user_id) as aggregate')
                ->value('aggregate'),
        ];
    }

    /**
     * @return Collection<int, TrainingEventAttempt>
     */
    public function eventCompletedAttempts(int $definitionId): Collection
    {
        return TrainingEventAttempt::query()
            ->with(['user.player'])
            ->where('definition_id', $definitionId)
            ->where('status', TrainingEventAttemptStatus::Completed)
            ->orderByDesc('score')
            ->orderBy('completed_at')
            ->limit(200)
            ->get();
    }

    /**
     * @return list<array{date: string, completed: int}>
     */
    public function eventDailyCompleted(int $definitionId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = TrainingEventAttempt::query()
            ->where('definition_id', $definitionId)
            ->where('status', TrainingEventAttemptStatus::Completed)
            ->whereBetween('completed_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        return $this->fillDays($from, $to, $rows);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $rows
     * @return list<array{date: string, completed: int}>
     */
    private function fillDays(CarbonImmutable $from, CarbonImmutable $to, $rows): array
    {
        $out = [];
        $cursor = $from->startOfDay();
        $end = $to->startOfDay();
        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $out[] = [
                'date' => $key,
                'completed' => (int) ($rows[$key] ?? 0),
            ];
            $cursor = $cursor->addDay();
        }

        return $out;
    }
}
