<?php

namespace App\Http\Controllers;

use App\Domain\Activity\FollowSubject;
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

    public function followOrganization(Organization $organization): RedirectResponse
    {
        return $this->change(FollowSubject::Organization, (string) $organization->id, true, 'Obserwujesz tę organizację.');
    }

    public function unfollowOrganization(Organization $organization): RedirectResponse
    {
        return $this->change(FollowSubject::Organization, (string) $organization->id, false, 'Przestałeś obserwować tę organizację.');
    }

    public function followPlayer(Player $player): RedirectResponse
    {
        if ($player->user_id === null) {
            return back()->with('error', 'Ten gracz nie ma konta.');
        }

        return $this->change(FollowSubject::User, (string) $player->user_id, true, 'Obserwujesz tego gracza.');
    }

    public function unfollowPlayer(Player $player): RedirectResponse
    {
        if ($player->user_id === null) {
            return back()->with('error', 'Ten gracz nie ma konta.');
        }

        return $this->change(FollowSubject::User, (string) $player->user_id, false, 'Przestałeś obserwować tego gracza.');
    }

    private function change(FollowSubject $subject, string $key, bool $follow, string $success): RedirectResponse
    {
        try {
            if ($follow) {
                $this->feed->follow(Auth::user(), $subject, $key);
            } else {
                $this->feed->unfollow(Auth::user(), $subject, $key);
            }
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $success);
    }
}
