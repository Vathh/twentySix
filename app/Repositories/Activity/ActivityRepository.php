<?php

namespace App\Repositories\Activity;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\ActivityDraft;
use App\Domain\Activity\ActivityRegistry;
use App\Models\Activity\Activity;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class ActivityRepository
{
    public function __construct(
        private ActivityRegistry $registry,
        private ActivityReactionRepository $reactions,
    ) {}

    public function save(ActivityDraft $draft): void
    {
        $existing = $this->findBySubject($draft);

        if ($existing !== null) {
            if (! $draft->replaceExisting) {
                return;
            }

            $existing->fill([
                'actor_player_id' => $draft->actorPlayerId,
                'actor_user_id' => $draft->actorUserId,
                'organization_id' => $draft->organizationId,
                'payload' => $draft->payload,
            ])->save();

            return;
        }

        try {
            Activity::query()->create([
                'type' => $draft->type->value,
                'actor_player_id' => $draft->actorPlayerId,
                'actor_user_id' => $draft->actorUserId,
                'organization_id' => $draft->organizationId,
                'subject_type' => $draft->subjectType,
                'subject_id' => $draft->subjectId,
                'payload' => $draft->payload,
                'occurred_at' => $draft->occurredAt,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            if ($draft->replaceExisting) {
                $row = $this->findBySubject($draft);
                $row?->fill([
                    'actor_player_id' => $draft->actorPlayerId,
                    'actor_user_id' => $draft->actorUserId,
                    'organization_id' => $draft->organizationId,
                    'payload' => $draft->payload,
                ])->save();
            }
        }
    }

    /**
     * Reguły kanałów: App\Domain\Activity\HomeFeedPolicy.
     * Suma warunków zwraca każdy wiersz raz.
     *
     * @param  list<int>  $friendIds
     * @param  list<int>  $organizationIds
     * @param  list<int>  $mutedUserIds
     * @param  list<int>  $mutedOrganizationIds
     * @return array{items: Collection<int, Activity>, hasMore: bool}
     */
    public function homePage(
        int $viewerId,
        bool $friendsChannelEnabled,
        bool $organizationChannelEnabled,
        array $friendIds,
        array $organizationIds,
        array $mutedUserIds,
        array $mutedOrganizationIds,
        ?CarbonInterface $cursorAt,
        ?int $cursorId,
        int $limit,
    ): array {
        $friendTypes = $this->registry->typesFor(ActivityChannel::Friends);
        $organizationTypes = $this->registry->typesFor(ActivityChannel::Organization);
        $visibleFriends = array_values(array_diff($friendIds, $mutedUserIds));
        $visibleOrganizations = $organizationChannelEnabled
            ? array_values(array_diff($organizationIds, $mutedOrganizationIds))
            : [];

        $query = Activity::query()->where(function ($outer) use (
            $viewerId,
            $friendsChannelEnabled,
            $friendTypes,
            $organizationTypes,
            $visibleFriends,
            $visibleOrganizations,
        ) {
            if ($friendTypes !== []) {
                $outer->where(function ($friends) use ($viewerId, $friendsChannelEnabled, $friendTypes, $visibleFriends) {
                    $friends->whereIn('type', $friendTypes)
                        ->whereNotNull('actor_user_id')
                        ->where(function ($who) use ($viewerId, $friendsChannelEnabled, $visibleFriends) {
                            $who->where('actor_user_id', $viewerId);
                            if ($friendsChannelEnabled && $visibleFriends !== []) {
                                $who->orWhereIn('actor_user_id', $visibleFriends);
                            }
                        });
                });
            }

            if ($organizationTypes !== [] && $visibleOrganizations !== []) {
                $method = $friendTypes === [] ? 'where' : 'orWhere';
                $outer->{$method}(function ($organizations) use ($organizationTypes, $visibleOrganizations) {
                    $organizations->whereIn('type', $organizationTypes)
                        ->whereIn('organization_id', $visibleOrganizations);
                });
            }
        });

        if ($cursorAt !== null && $cursorId !== null) {
            $query->where(function ($cursor) use ($cursorAt, $cursorId) {
                $cursor->where('occurred_at', '<', $cursorAt)
                    ->orWhere(function ($sameMoment) use ($cursorAt, $cursorId) {
                        $sameMoment->where('occurred_at', $cursorAt)
                            ->where('id', '<', $cursorId);
                    });
            });
        }

        $rows = $query
            ->with('actorPlayer.user')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;

        return [
            'items' => $rows->take($limit)->values(),
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @return array{items: Collection<int, Activity>, hasMore: bool}
     */
    public function profilePage(
        int $playerId,
        ?CarbonInterface $cursorAt,
        ?int $cursorId,
        int $limit,
    ): array {
        $query = Activity::query()->where('actor_player_id', $playerId);

        if ($cursorAt !== null && $cursorId !== null) {
            $query->where(function ($cursor) use ($cursorAt, $cursorId) {
                $cursor->where('occurred_at', '<', $cursorAt)
                    ->orWhere(function ($sameMoment) use ($cursorAt, $cursorId) {
                        $sameMoment->where('occurred_at', $cursorAt)
                            ->where('id', '<', $cursorId);
                    });
            });
        }

        $rows = $query
            ->with('actorPlayer.user')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        return [
            'items' => $rows->take($limit)->values(),
            'hasMore' => $rows->count() > $limit,
        ];
    }

    public function deleteSubject(string $type, string $subjectType, int $subjectId, ?int $actorPlayerId = null): void
    {
        $query = Activity::query()
            ->where('type', $type)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId);

        if ($actorPlayerId !== null) {
            $query->where('actor_player_id', $actorPlayerId);
        }

        $ids = (clone $query)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->reactions->forgetActivities($ids);
        $query->delete();
    }

    private function findBySubject(ActivityDraft $draft): ?Activity
    {
        $query = Activity::query()
            ->where('type', $draft->type->value)
            ->where('subject_type', $draft->subjectType)
            ->where('subject_id', $draft->subjectId);

        if ($draft->matchActor) {
            $query->where('actor_player_id', $draft->actorPlayerId);
        }

        return $query->first();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return $sqlState === '23000';
    }
}
