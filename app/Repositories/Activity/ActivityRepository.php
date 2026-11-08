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

        $this->insert($draft);
    }

    /**
     * Zapis albo przesunięcie daty istniejącej aktywności (np. kolejne 180 po cofnięciu).
     */
    public function saveOccurredAt(ActivityDraft $draft): void
    {
        $existing = $this->findBySubject($draft);
        if ($existing === null) {
            $this->save($draft);

            return;
        }

        $existing->occurred_at = $draft->occurredAt;
        $existing->save();
    }

    private function insert(ActivityDraft $draft): void
    {
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
     * @param  list<int>  $followedUserIds
     * @param  list<int>  $followedOrganizationIds
     * @param  list<int>  $blockedUserIds
     * @return array{items: Collection<int, Activity>, hasMore: bool}
     */
    public function homePage(
        int $viewerId,
        bool $friendsChannelEnabled,
        array $followedUserIds,
        array $followedOrganizationIds,
        array $blockedUserIds,
        ?CarbonInterface $cursorAt,
        ?int $cursorId,
        int $limit,
    ): array {
        $friendTypes = $this->registry->typesFor(ActivityChannel::Friends);
        $organizationTypes = $this->registry->typesFor(ActivityChannel::Organization);
        $visibleUsers = $friendsChannelEnabled ? $followedUserIds : [];

        $query = Activity::query()->where(function ($outer) use (
            $viewerId,
            $friendTypes,
            $organizationTypes,
            $visibleUsers,
            $followedOrganizationIds,
        ) {
            if ($friendTypes !== []) {
                $outer->where(function ($friends) use ($viewerId, $friendTypes, $visibleUsers) {
                    $friends->whereIn('type', $friendTypes)
                        ->whereNotNull('actor_user_id')
                        ->where(function ($who) use ($viewerId, $visibleUsers) {
                            $who->where('actor_user_id', $viewerId);
                            if ($visibleUsers !== []) {
                                $who->orWhereIn('actor_user_id', $visibleUsers);
                            }
                        });
                });
            }

            if ($organizationTypes !== [] && $followedOrganizationIds !== []) {
                $method = $friendTypes === [] ? 'where' : 'orWhere';
                $outer->{$method}(function ($organizations) use ($organizationTypes, $followedOrganizationIds) {
                    $organizations->whereIn('type', $organizationTypes)
                        ->whereIn('organization_id', $followedOrganizationIds);
                });
            }
        });

        if ($blockedUserIds !== []) {
            $query->where(function ($hidden) use ($blockedUserIds) {
                $hidden->whereNull('actor_user_id')
                    ->orWhereNotIn('actor_user_id', $blockedUserIds);
            });
        }

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
            ->with(['actorPlayer.user', 'organization'])
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
            ->with(['actorPlayer.user', 'organization'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        return [
            'items' => $rows->take($limit)->values(),
            'hasMore' => $rows->count() > $limit,
        ];
    }

    public function find(int $id): ?Activity
    {
        return Activity::query()->find($id);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function sentencesFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $sentences = [];
        foreach (Activity::query()->whereIn('id', $ids)->get() as $activity) {
            $payload = is_array($activity->payload) ? $activity->payload : [];
            $sentences[(int) $activity->id] = $this->registry->sentence((string) $activity->type, $payload);
        }

        return $sentences;
    }

    public function deleteById(int $id): void
    {
        if (! Activity::query()->whereKey($id)->exists()) {
            return;
        }

        $this->reactions->forgetActivities([$id]);
        Activity::query()->whereKey($id)->delete();
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
