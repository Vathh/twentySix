<?php

namespace App\Http\Controllers;

use App\Services\Platform\PlatformAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    public function __construct(
        private PlatformAdminService $platformAdminService,
    ) {
    }

    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'stats' => $this->platformAdminService->dashboard(),
        ]);
    }

    public function users(Request $request): View
    {
        $search = $request->string('q')->toString();

        return view('admin.users', [
            'users' => $this->platformAdminService->listUsers($search !== '' ? $search : null),
            'search' => $search,
        ]);
    }

    public function showUser(int $userId): View
    {
        $detail = $this->platformAdminService->userDetail($userId);

        return view('admin.user', [
            'user' => $detail['user'],
            'activity' => $detail['activity'],
            'recentGames' => $detail['recentGames'],
        ]);
    }

    public function updateCanCreateLeagues(Request $request, int $userId): RedirectResponse
    {
        $validated = $request->validate([
            'can_create_leagues' => ['required', 'boolean'],
        ]);

        $user = $this->platformAdminService->setCanCreateLeagues(
            $userId,
            (bool) $validated['can_create_leagues'],
        );

        $label = $user->player?->name ?? $user->email;
        $state = $user->can_create_leagues ? 'włączone' : 'wyłączone';

        return back()->with('success', "Tworzenie lig dla {$label}: {$state}");
    }

    public function updateBanned(Request $request, int $userId): RedirectResponse
    {
        $validated = $request->validate([
            'banned' => ['required', 'boolean'],
        ]);

        try {
            $user = $this->platformAdminService->setBanned(
                $userId,
                (bool) $validated['banned'],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $label = $user->player?->name ?? $user->email;
        $state = $user->isBanned() ? 'zablokowane' : 'odblokowane';

        return back()->with('success', "Konto {$label}: {$state}");
    }
}
