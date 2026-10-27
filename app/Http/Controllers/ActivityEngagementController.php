<?php

namespace App\Http\Controllers;

use App\Domain\Activity\ActivityReactionKind;
use App\Models\Activity\Activity;
use App\Models\Activity\ActivityComment;
use App\Services\Activity\ActivityEngagementService;
use App\Services\Activity\ActivityHiddenException;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityEngagementController extends Controller
{
    public function __construct(private ActivityEngagementService $engagement) {}

    public function comments(Request $request, Activity $activity): JsonResponse
    {
        return $this->respond(fn () => $this->engagement->comments($request->user(), $activity));
    }

    public function storeComment(Request $request, Activity $activity): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:500'],
            'parentId' => ['nullable', 'integer'],
        ], [
            'body.required' => 'Wpisz komentarz.',
            'body.max' => 'Komentarz może mieć najwyżej 500 znaków.',
        ]);

        return $this->respond(fn () => $this->engagement->addComment(
            $request->user(),
            $activity,
            $data['body'],
            isset($data['parentId']) ? (int) $data['parentId'] : null,
        ));
    }

    public function destroyComment(Request $request, ActivityComment $comment): JsonResponse
    {
        return $this->respond(fn () => $this->engagement->deleteComment($request->user(), $comment));
    }

    public function react(Request $request, Activity $activity): JsonResponse
    {
        return $this->respond(fn () => $this->engagement->react(
            $request->user(),
            $activity,
            $this->kind($request),
        ));
    }

    public function reactToComment(Request $request, ActivityComment $comment): JsonResponse
    {
        return $this->respond(fn () => $this->engagement->reactToComment(
            $request->user(),
            $comment,
            $this->kind($request),
        ));
    }

    private function kind(Request $request): ?ActivityReactionKind
    {
        $data = $request->validate([
            'kind' => ['nullable', Rule::enum(ActivityReactionKind::class)],
        ]);

        $kind = $data['kind'] ?? null;

        return $kind === null ? null : ActivityReactionKind::from($kind);
    }

    /**
     * @param  callable(): array<string, mixed>  $action
     */
    private function respond(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (ActivityHiddenException) {
            return response()->json(['message' => 'Nie ma takiej historii.'], 404);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
