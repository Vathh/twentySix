<?php

namespace App\Http\Controllers;

use App\Domain\Activity\FeedMuteSubject;
use App\Domain\Activity\ProfileFeedVisibility;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Services\Activity\FeedService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityFeedWebController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function edit(): View
    {
        return view('settings.feed', [
            'preferences' => $this->feed->preferences(Auth::user()),
            'visibilityOptions' => ProfileFeedVisibility::cases(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile_feed_visibility' => ['required', Rule::enum(ProfileFeedVisibility::class)],
        ]);

        $this->feed->updatePreferences(
            $request->user(),
            ProfileFeedVisibility::from($data['profile_feed_visibility']),
            $request->boolean('channel_friends'),
            $request->boolean('channel_organization'),
        );

        return redirect()
            ->route('settings.feed.edit')
            ->with('success', 'Ustawienia tablicy zostały zapisane.');
    }

    public function muteOrganization(Organization $organization): RedirectResponse
    {
        return $this->change(FeedMuteSubject::Organization, (string) $organization->id, mute: true);
    }

    public function unmuteOrganization(Organization $organization): RedirectResponse
    {
        return $this->change(FeedMuteSubject::Organization, (string) $organization->id, mute: false);
    }

    public function mutePlayer(Player $player): RedirectResponse
    {
        if ($player->user_id === null) {
            return back()->with('error', 'Ten gracz nie ma konta.');
        }

        return $this->change(FeedMuteSubject::User, (string) $player->user_id, mute: true);
    }

    public function unmutePlayer(Player $player): RedirectResponse
    {
        if ($player->user_id === null) {
            return back()->with('error', 'Ten gracz nie ma konta.');
        }

        return $this->change(FeedMuteSubject::User, (string) $player->user_id, mute: false);
    }

    private function change(FeedMuteSubject $subject, string $key, bool $mute): RedirectResponse
    {
        try {
            if ($mute) {
                $this->feed->mute(Auth::user(), $subject, $key);
            } else {
                $this->feed->unmute(Auth::user(), $subject, $key);
            }
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $mute ? 'Wyciszono na stronie głównej.' : 'Wyciszenie zdjęte.');
    }
}
