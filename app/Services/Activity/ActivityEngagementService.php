<?php

namespace App\Services\Activity;

use App\Domain\Activity\ActivityCommentRules;
use App\Domain\Activity\ActivityReactionKind;
use App\Domain\Activity\ActivityReactionSubject;
use App\Domain\Activity\ActivityReactionToggle;
use App\Models\Activity\Activity;
use App\Models\Activity\ActivityComment;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Activity\ActivityCommentRepository;
use App\Repositories\Activity\ActivityReactionRepository;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class ActivityEngagementService
{
    public function __construct(
        private ActivityCommentRepository $comments,
        private ActivityReactionRepository $reactions,
        private ProfileBoardGate $board,
        private ActivityCommentRules $commentRules,
        private ActivityReactionToggle $toggle,
    ) {}

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<int, array{commentCount: int, counts: array<string, int>, mine: ?string, canEngage: bool}>
     */
    public function overlay(Collection $activities, ?User $viewer): array
    {
        $ids = $activities->map(fn (Activity $activity) => (int) $activity->id)->all();
        $commentCounts = $this->comments->countsFor($ids);
        $reactionSummary = $this->reactions->summary(ActivityReactionSubject::Activity, $ids, $viewer?->id);
        $allowed = $this->allowedActors($activities, $viewer);

        $overlay = [];
        foreach ($activities as $activity) {
            $id = (int) $activity->id;
            $summary = $reactionSummary[$id] ?? ['counts' => [], 'mine' => null];
            $overlay[$id] = [
                'commentCount' => $commentCounts[$id] ?? 0,
                'counts' => $summary['counts'],
                'mine' => $summary['mine'],
                'canEngage' => $allowed[$this->actorKey($activity)] ?? false,
            ];
        }

        return $overlay;
    }

    /**
     * @return array{comments: list<array<string, mixed>>}
     */
    public function comments(User $viewer, Activity $activity): array
    {
        $activity = $this->visible($viewer, $activity);
        $rows = $this->comments->forActivity($activity->id);
        $summary = $this->reactions->summary(
            ActivityReactionSubject::Comment,
            $rows->map(fn (ActivityComment $comment) => (int) $comment->id)->all(),
            $viewer->id,
        );

        return ['comments' => $this->thread($rows, $summary, $viewer->id)];
    }

    /**
     * @return array{comment: array<string, mixed>, commentCount: int}
     */
    public function addComment(User $viewer, Activity $activity, string $body, ?int $parentId): array
    {
        $activity = $this->visible($viewer, $activity);
        $body = $this->commentRules->normalizedBody($body);
        if ($body === '') {
            throw new DomainException('Wpisz komentarz.');
        }

        if ($parentId !== null) {
            $parent = $this->comments->find($parentId);
            if (! $this->commentRules->parentFits($parent, (int) $activity->id)) {
                throw new DomainException('Odpowiedź może być tylko do komentarza pod tym wpisem.');
            }
        }

        $comment = $this->comments->create((int) $activity->id, $viewer->id, $parentId, $body);

        return [
            'comment' => $this->presentComment($comment, $viewer->id, [], []),
            'commentCount' => $this->comments->countFor((int) $activity->id),
        ];
    }

    /**
     * @return array{commentCount: int}
     */
    public function deleteComment(User $viewer, ActivityComment $comment): array
    {
        $comment = $this->comments->find((int) $comment->id);
        $activity = $comment?->activity;
        if ($comment === null || $activity === null) {
            throw new ActivityHiddenException;
        }

        $this->visible($viewer, $activity);

        if ((int) $comment->user_id !== (int) $viewer->id) {
            throw new AuthorizationException('Możesz usunąć tylko swój komentarz.');
        }

        $activityId = (int) $comment->activity_id;
        $this->comments->deleteWithReplies($comment);

        return ['commentCount' => $this->comments->countFor($activityId)];
    }

    /**
     * @return array{reactions: array{counts: array<string, int>|object, mine: ?string}}
     */
    public function react(User $viewer, Activity $activity, ?ActivityReactionKind $kind): array
    {
        $activity = $this->visible($viewer, $activity);

        return [
            'reactions' => $this->applyReaction(
                $viewer,
                ActivityReactionSubject::Activity,
                (int) $activity->id,
                $kind,
            ),
        ];
    }

    /**
     * @return array{reactions: array{counts: array<string, int>|object, mine: ?string}}
     */
    public function reactToComment(User $viewer, ActivityComment $comment, ?ActivityReactionKind $kind): array
    {
        $comment = $this->comments->find((int) $comment->id);
        $activity = $comment?->activity;
        if ($comment === null || $activity === null) {
            throw new ActivityHiddenException;
        }

        $this->visible($viewer, $activity);

        return [
            'reactions' => $this->applyReaction(
                $viewer,
                ActivityReactionSubject::Comment,
                (int) $comment->id,
                $kind,
            ),
        ];
    }

    /**
     * @return array{counts: array<string, int>|object, mine: ?string}
     */
    private function applyReaction(
        User $viewer,
        ActivityReactionSubject $subject,
        int $subjectId,
        ?ActivityReactionKind $kind,
    ): array {
        $next = $this->toggle->next(
            $this->reactions->mine($viewer->id, $subject, $subjectId),
            $kind,
        );
        $this->reactions->put($viewer->id, $subject, $subjectId, $next);
        $summary = $this->reactions->summary($subject, [$subjectId], $viewer->id)[$subjectId]
            ?? ['counts' => [], 'mine' => null];

        return $this->reactionPayload($summary['counts'], $summary['mine']);
    }

    private function visible(User $viewer, Activity $activity): Activity
    {
        $activity->loadMissing('actorPlayer.user');
        $player = $activity->actorPlayer;
        if (! $player instanceof Player || ! $this->board->allows($viewer, $player)) {
            throw new ActivityHiddenException;
        }

        return $activity;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<string, bool>
     */
    private function allowedActors(Collection $activities, ?User $viewer): array
    {
        $allowed = [];
        foreach ($activities as $activity) {
            $key = $this->actorKey($activity);
            if (array_key_exists($key, $allowed)) {
                continue;
            }

            $player = $activity->actorPlayer;
            $allowed[$key] = $viewer !== null
                && $player instanceof Player
                && $this->board->allows($viewer, $player);
        }

        return $allowed;
    }

    private function actorKey(Activity $activity): string
    {
        return $activity->actor_user_id === null
            ? 'player:'.$activity->actor_player_id
            : 'user:'.$activity->actor_user_id;
    }

    /**
     * @param  Collection<int, ActivityComment>  $rows
     * @param  array<int, array{counts: array<string, int>, mine: ?string}>  $summary
     * @return list<array<string, mixed>>
     */
    private function thread(Collection $rows, array $summary, int $viewerId): array
    {
        $replies = [];
        $roots = [];
        foreach ($rows as $comment) {
            if ($comment->parent_id === null) {
                $roots[] = $comment;
            } else {
                $replies[(int) $comment->parent_id][] = $comment;
            }
        }

        return array_map(
            fn (ActivityComment $comment) => $this->presentComment(
                $comment,
                $viewerId,
                $summary,
                $replies[(int) $comment->id] ?? [],
            ),
            $roots,
        );
    }

    /**
     * @param  array<int, array{counts: array<string, int>, mine: ?string}>  $summary
     * @param  list<ActivityComment>  $replies
     * @return array<string, mixed>
     */
    private function presentComment(ActivityComment $comment, int $viewerId, array $summary, array $replies): array
    {
        $user = $comment->user;
        $player = $user?->player;
        $name = (string) ($player->name ?? 'Gracz');
        $mine = $summary[(int) $comment->id] ?? ['counts' => [], 'mine' => null];

        return [
            'id' => (int) $comment->id,
            'parentId' => $comment->parent_id === null ? null : (int) $comment->parent_id,
            'body' => $comment->body,
            'createdAt' => $comment->created_at?->toIso8601String(),
            'canDelete' => $user !== null && (int) $user->id === $viewerId,
            'author' => [
                'userId' => $user === null ? null : (int) $user->id,
                'playerId' => $player === null ? null : (int) $player->id,
                'name' => $name,
                'initials' => $player?->initials() ?? Player::initialsFromName($name),
                'avatarUrl' => $player?->avatarUrl(),
            ],
            'reactions' => $this->reactionPayload($mine['counts'], $mine['mine']),
            'replies' => array_map(
                fn (ActivityComment $reply) => $this->presentComment($reply, $viewerId, $summary, []),
                $replies,
            ),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{counts: array<string, int>|object, mine: ?string}
     */
    private function reactionPayload(array $counts, ?string $mine): array
    {
        return [
            'counts' => $counts === [] ? (object) [] : $counts,
            'mine' => $mine,
        ];
    }
}
