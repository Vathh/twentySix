<?php

namespace App\Services\Activity;

use App\Domain\Activity\ActivityDraft;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\Definitions\PlayerFirst180Activity;
use App\Repositories\Activity\ActivityRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Repositories\QuickGame\QuickGameFfaVisitRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ActivityRecorder
{
    public function __construct(
        private ActivityDraftFactory $drafts,
        private ActivityRepository $activities,
        private GameVisitRepository $gameVisits,
        private QuickGameFfaVisitRepository $ffaVisits,
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

    public function tournamentAnnounced(int $tournamentId): void
    {
        $this->afterCommit(function () use ($tournamentId) {
            $draft = $this->drafts->tournamentAnnounced($tournamentId);
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

    /**
     * Po wykreśleniu wizyty 180: zostaw najwcześniejszą pozostałą albo usuń wpis.
     */
    public function syncPlayerFirst180(int $playerId): void
    {
        $this->afterCommit(function () use ($playerId) {
            $occurredAt = $this->earliestActive180At($playerId);
            $definition = new PlayerFirst180Activity;
            if ($occurredAt === null) {
                $this->activities->deleteSubject(
                    ActivityType::PlayerFirst180->value,
                    $definition->subjectType(),
                    $playerId,
                    $playerId,
                );

                return;
            }

            $draft = $this->drafts->playerFirst180($playerId, Carbon::parse($occurredAt));
            if ($draft === null) {
                $this->activities->deleteSubject(
                    ActivityType::PlayerFirst180->value,
                    $definition->subjectType(),
                    $playerId,
                    $playerId,
                );

                return;
            }

            $this->activities->saveOccurredAt($draft);
        });
    }

    private function earliestActive180At(int $playerId): ?string
    {
        $times = array_filter([
            $this->gameVisits->earliestActive180At($playerId),
            $this->ffaVisits->earliestActive180At($playerId),
        ]);
        if ($times === []) {
            return null;
        }

        sort($times);

        return $times[0];
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
