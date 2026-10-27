<?php

namespace App\Services\Activity;

use App\Domain\Activity\ActivityDraft;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\Definitions\PlayerBadgesActivity;
use App\Domain\Activity\Definitions\PlayerFirst180Activity;
use App\Domain\Activity\Definitions\PlayerPersonalBestActivity;
use App\Domain\Activity\Definitions\TournamentChampionActivity;
use App\Domain\PlayerDomain;
use App\Enums\TournamentStatus;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Repositories\Tournament\TournamentResultRepository;
use Carbon\Carbon;

class ActivityDraftFactory
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private TournamentResultRepository $tournamentResultRepository,
        private PlayerRepository $playerRepository,
    ) {}

    public function tournamentChampion(int $tournamentId, ?Carbon $occurredAt = null): ?ActivityDraft
    {
        $playerId = $this->tournamentResultRepository->championPlayerId($tournamentId);
        if ($playerId === null) {
            return null;
        }

        $player = $this->playerRepository->findById($playerId);
        if ($player === null || $player->isBye) {
            return null;
        }

        $tournament = $this->tournamentRepository->findModelOrNull($tournamentId, ['season.organization']);
        if ($tournament === null) {
            return null;
        }

        $organization = $tournament->season?->organization;
        $definition = new TournamentChampionActivity;

        return new ActivityDraft(
            type: ActivityType::TournamentChampion,
            subjectType: $definition->subjectType(),
            subjectId: $tournamentId,
            actorPlayerId: $player->id,
            actorUserId: $player->userId,
            organizationId: $organization?->id,
            payload: [
                'actorName' => $player->name,
                'tournamentId' => $tournamentId,
                'tournamentName' => $tournament->name,
                'organizationId' => $organization?->id,
                'organizationName' => $organization?->name ?? '',
            ],
            occurredAt: $occurredAt ?? $tournament->updated_at ?? Carbon::now(),
            replaceExisting: $definition->replacesExisting(),
        );
    }

    public function playerFirst180(int $playerId, ?Carbon $occurredAt = null): ?ActivityDraft
    {
        $player = $this->eligiblePlayer($playerId);
        if ($player === null) {
            return null;
        }

        $definition = new PlayerFirst180Activity;

        return new ActivityDraft(
            type: ActivityType::PlayerFirst180,
            subjectType: $definition->subjectType(),
            subjectId: $player->id,
            actorPlayerId: $player->id,
            actorUserId: $player->userId,
            organizationId: null,
            payload: [
                'actorName' => $player->name,
                'playerId' => $player->id,
            ],
            occurredAt: $occurredAt ?? Carbon::now(),
            replaceExisting: $definition->replacesExisting(),
        );
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
    ): ?ActivityDraft {
        if ($badges === []) {
            return null;
        }

        $player = $this->eligiblePlayer($playerId);
        if ($player === null) {
            return null;
        }

        $definition = new PlayerBadgesActivity;

        return new ActivityDraft(
            type: ActivityType::PlayerBadges,
            subjectType: $subjectType,
            subjectId: $subjectId,
            actorPlayerId: $player->id,
            actorUserId: $player->userId,
            organizationId: null,
            payload: [
                'actorName' => $player->name,
                'playerId' => $player->id,
                'badges' => $badges,
            ],
            occurredAt: $occurredAt ?? Carbon::now(),
            replaceExisting: $definition->replacesExisting(),
            matchActor: true,
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
    ): ?ActivityDraft {
        if ($records === []) {
            return null;
        }

        $player = $this->eligiblePlayer($playerId);
        if ($player === null) {
            return null;
        }

        $definition = new PlayerPersonalBestActivity;

        return new ActivityDraft(
            type: ActivityType::PlayerPersonalBest,
            subjectType: $subjectType,
            subjectId: $subjectId,
            actorPlayerId: $player->id,
            actorUserId: $player->userId,
            organizationId: null,
            payload: [
                'actorName' => $player->name,
                'playerId' => $player->id,
                'records' => $records,
            ],
            occurredAt: $occurredAt ?? Carbon::now(),
            replaceExisting: $definition->replacesExisting(),
            matchActor: true,
        );
    }

    private function eligiblePlayer(int $playerId): ?PlayerDomain
    {
        $player = $this->playerRepository->findById($playerId);
        if ($player === null || $player->userId === null || $player->isBye) {
            return null;
        }

        return $player;
    }

    public function tournamentIsFinished(int $tournamentId): bool
    {
        $tournament = $this->tournamentRepository->findModelOrNull($tournamentId);

        return $tournament !== null && $tournament->status === TournamentStatus::FINISHED;
    }
}
