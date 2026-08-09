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
}
