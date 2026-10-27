<?php

namespace App\Services\Activity;

use App\Domain\Activity\ActivityDraft;
use App\Domain\Activity\ActivityType;
use App\Repositories\Activity\ActivityRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ActivityRecorder
{
    public function __construct(
        private ActivityDraftFactory $drafts,
        private ActivityRepository $activities,
    ) {}

    public function tournamentChampion(int $tournamentId, ?Carbon $occurredAt = null): void
    {
        $this->afterCommit(function () use ($tournamentId, $occurredAt) {
            $draft = $this->drafts->tournamentChampion($tournamentId, $occurredAt);
            if ($draft === null) {
                return;
            }

            $this->activities->save($draft);
        });
    }

    public function tournamentChampionIfFinished(int $tournamentId): void
    {
        $this->afterCommit(function () use ($tournamentId) {
            if (! $this->drafts->tournamentIsFinished($tournamentId)) {
                return;
            }

            $draft = $this->drafts->tournamentChampion($tournamentId);
            if ($draft === null) {
                return;
            }

            $this->activities->save($draft);
        });
    }

    /**
     * @param  list<array{category: string, key: string, levelName: string, levelLabel: string, title: string}>  $badges
     */
    public function playerBadges(
        int $playerId,
        string $subjectType,
        int $subjectId,
        array $badges,
        ?Carbon $occurredAt = null,
    ): void {
        $this->storeOrForget(
            ActivityType::PlayerBadges,
            $subjectType,
            $subjectId,
            $playerId,
            $this->drafts->playerBadges($playerId, $subjectType, $subjectId, $badges, $occurredAt),
        );
    }

    /**
     * @param  list<array{kind: string, value: float|int}>  $records
     */
    public function playerPersonalBest(
        int $playerId,
        string $subjectType,
        int $subjectId,
        array $records,
        ?Carbon $occurredAt = null,
    ): void {
        $this->storeOrForget(
            ActivityType::PlayerPersonalBest,
            $subjectType,
            $subjectId,
            $playerId,
            $this->drafts->playerPersonalBest($playerId, $subjectType, $subjectId, $records, $occurredAt),
        );
    }

    public function forgetSubject(string $subjectType, int $subjectId): void
    {
        $this->afterCommit(function () use ($subjectType, $subjectId) {
            $this->activities->deleteSubject(ActivityType::PlayerBadges->value, $subjectType, $subjectId);
            $this->activities->deleteSubject(ActivityType::PlayerPersonalBest->value, $subjectType, $subjectId);
        });
    }

    public function playerFirst180(int $playerId, ?Carbon $occurredAt = null): void
    {
        $this->afterCommit(function () use ($playerId, $occurredAt) {
            $draft = $this->drafts->playerFirst180($playerId, $occurredAt);
            if ($draft === null) {
                return;
            }

            $this->activities->save($draft);
        });
    }

    private function storeOrForget(
        ActivityType $type,
        string $subjectType,
        int $subjectId,
        int $playerId,
        ?ActivityDraft $draft,
    ): void {
        $this->afterCommit(function () use ($type, $subjectType, $subjectId, $playerId, $draft) {
            if ($draft === null) {
                $this->activities->deleteSubject($type->value, $subjectType, $subjectId, $playerId);

                return;
            }

            $this->activities->save($draft);
        });
    }

    private function afterCommit(callable $callback): void
    {
        DB::afterCommit(function () use ($callback) {
            try {
                $callback();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
