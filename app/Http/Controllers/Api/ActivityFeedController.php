<?php

namespace App\Http\Controllers\Api;

use App\Domain\Activity\FeedMuteSubject;
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

    public function muteState(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subjectType' => ['required', Rule::enum(FeedMuteSubject::class)],
            'subjectKey' => ['required', 'string', 'max:32'],
        ]);

        return response()->json($this->feed->muteState(
            $request->user(),
            FeedMuteSubject::from($data['subjectType']),
            $data['subjectKey'],
        ));
    }

    public function mute(Request $request): JsonResponse
    {
        return $this->changeMute($request, mute: true);
    }

    public function unmute(Request $request): JsonResponse
    {
        return $this->changeMute($request, mute: false);
    }

    private function changeMute(Request $request, bool $mute): JsonResponse
    {
        $data = $request->validate([
            'subjectType' => ['required', Rule::enum(FeedMuteSubject::class)],
            'subjectKey' => ['required', 'string', 'max:32'],
        ]);

        $subject = FeedMuteSubject::from($data['subjectType']);

        try {
            if ($mute) {
                $this->feed->mute($request->user(), $subject, $data['subjectKey']);
            } else {
                $this->feed->unmute($request->user(), $subject, $data['subjectKey']);
            }
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($this->feed->muteState($request->user(), $subject, $data['subjectKey']));
    }
}
