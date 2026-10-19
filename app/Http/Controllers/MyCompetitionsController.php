<?php

namespace App\Http\Controllers;

use App\Models\Organization\Organization;
use App\Services\Organization\OrganizationInvitationService;
use App\Services\User\UserCompetitionsService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MyCompetitionsController extends Controller
{
    public function __construct(
        private UserCompetitionsService $userCompetitionsService,
        private OrganizationInvitationService $organizationInvitationService,
    ) {}

    public function index(): Factory|View
    {
        return view('me.index', $this->userCompetitionsService->forUser(Auth::user(), withUrls: true));
    }

    public function leaveOrganization(Organization $organization): RedirectResponse
    {
        try {
            $this->organizationInvitationService->leave($organization->id, (int) Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Wypisano ze składu organizacji.');
    }
}
