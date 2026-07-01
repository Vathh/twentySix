<?php

namespace App\Http\Controllers;

use App\Models\Friends\FriendshipInvitation;
use App\Services\Friends\FriendshipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class FriendInvitationController extends Controller
{
    public function __construct(
        private FriendshipService $friendshipService,
    ) {
    }

    public function accept(FriendshipInvitation $invitation): RedirectResponse
    {
        try {
            $this->friendshipService->acceptInvitation($invitation->id, (int) Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Zaproszenie zaakceptowane — jesteście znajomymi.');
    }

    public function reject(FriendshipInvitation $invitation): RedirectResponse
    {
        try {
            $this->friendshipService->rejectInvitation($invitation->id, (int) Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Zaproszenie odrzucone.');
    }
}
