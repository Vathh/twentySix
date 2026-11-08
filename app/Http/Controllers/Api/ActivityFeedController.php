<?php

namespace App\Http\Controllers\Api;

use App\Domain\Activity\FollowSubject;
use App\Domain\Activity\ProfileFeedVisibility;
use App\Http\Controllers\Controller;
use App\Models\Player\Player;
use App\Services\Activity\FeedService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityFeedController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function home(Request $request): JsonResponse
    {
        return response()->json(
            $this->feed->home($request->user(), $request->query('cursor')),
        );
    }

    public function profile(Request $request, Player $player): JsonResponse
    {
        $viewer = $request->user('sanctum');

        return response()->json(
            $this->feed->profile($viewer, $player, $request->query('cursor')),
        );
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json($this->feed->preferences($request->user()));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profileFeedVisibility' => ['required', Rule::enum(ProfileFeedVisibility::class)],
            'channels.friends' => ['required', 'boolean'],
            'channels.organization' => ['required', 'boolean'],
        ]);

        $this->feed->updatePreferences(
            $request->user(),
            ProfileFeedVisibility::from($data['profileFeedVisibility']),
            (bool) $data['channels']['friends'],
            (bool) $data['channels']['organization'],
        );

        return response()->json($this->feed->preferences($request->user()));
    }

    public function followState(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subjectType' => ['required', Rule::enum(FollowSubject::class)],
            'subjectKey' => ['required', 'string', 'max:32'],
        ]);

        return response()->json($this->feed->followState(
            $request->user(),
            FollowSubject::from($data['subjectType']),
            $data['subjectKey'],
        ));
    }

    public function follow(Request $request): JsonResponse
    {
        return $this->changeFollow($request, follow: true);
    }

    public function unfollow(Request $request): JsonResponse
    {
        return $this->changeFollow($request, follow: false);
    }

    private function changeFollow(Request $request, bool $follow): JsonResponse
    {
        $data = $request->validate([
            'subjectType' => ['required', Rule::enum(FollowSubject::class)],
            'subjectKey' => ['required', 'string', 'max:32'],
        ]);

        $subject = FollowSubject::from($data['subjectType']);

        try {
            if ($follow) {
                $this->feed->follow($request->user(), $subject, $data['subjectKey']);
            } else {
                $this->feed->unfollow($request->user(), $subject, $data['subjectKey']);
            }
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($this->feed->followState($request->user(), $subject, $data['subjectKey']));
    }
}
