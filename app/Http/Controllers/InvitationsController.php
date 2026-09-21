<?php

namespace App\Http\Controllers;

use App\Models\Users\User;
use App\Services\Friends\FriendshipService;
use App\Services\League\LeagueGamePlayService;
use App\Services\League\LeagueInvitationService;
use App\Services\Organization\OrganizationInvitationService;
use App\Services\QuickGame\QuickGameLobbyService;
use App\Services\Season\SeasonInvitationService;
use App\Services\Tournament\TournamentInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvitationsController extends Controller
{
    public function __construct(
        private FriendshipService $friendshipService,
        private TournamentInvitationService $tournamentInvitationService,
        private OrganizationInvitationService $organizationInvitationService,
        private SeasonInvitationService $seasonInvitationService,
        private LeagueInvitationService $leagueInvitationService,
        private QuickGameLobbyService $quickGameLobbyService,
        private LeagueGamePlayService $leagueGamePlayService,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->user();
        $userId = $user->id;

        $friendInvitations = $this->friendshipService->getReceivedInvitations($userId);
        $tournamentInvitations = $this->tournamentInvitationService->getReceivedForUser($userId);
        $organizationInvitations = $this->organizationInvitationService->getReceivedForUser($userId);
        $seasonInvitations = $this->seasonInvitationService->getReceivedForUser($userId);
        $leagueInvitations = $this->leagueInvitationService->getReceivedForUser($userId);
        $quickGameInvitations = $this->quickGameLobbyService->getPendingInvitationsForUser($userId);
        $leagueGameInvitations = $user->player
            ? $this->leagueGamePlayService->invitations($user)['invitations']
            : [];

        $graCount = count($leagueGameInvitations)
            + $quickGameInvitations->count()
            + $tournamentInvitations->count()
            + $organizationInvitations->count()
            + $seasonInvitations->count()
            + $leagueInvitations->count();

        return view('invitations.index', [
            'tab' => $request->query('tab') === 'friends' ? 'friends' : 'gra',
            'graCount' => $graCount,
            'friendCount' => $friendInvitations->count(),
            'friendInvitations' => $friendInvitations,
            'tournamentInvitations' => $tournamentInvitations,
            'organizationInvitations' => $organizationInvitations,
            'seasonInvitations' => $seasonInvitations,
            'leagueInvitations' => $leagueInvitations,
            'quickGameInvitations' => $quickGameInvitations,
            'leagueGameInvitations' => $leagueGameInvitations,
        ]);
    }

    public function acceptTournament(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->tournamentInvitationService->accept($invitation, $this->user()->id),
            'Zaproszenie zostało zaakceptowane',
        );
    }

    public function rejectTournament(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->tournamentInvitationService->reject($invitation, $this->user()->id),
            'Zaproszenie zostało odrzucone',
        );
    }

    public function withdrawTournament(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->tournamentInvitationService->withdraw($invitation, $this->user()->id),
            'Wycofano udział w turnieju',
        );
    }

    public function acceptOrganization(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->organizationInvitationService->accept($invitation, $this->user()->id),
            'Zaproszenie do organizacji zostało zaakceptowane',
        );
    }

    public function rejectOrganization(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->organizationInvitationService->reject($invitation, $this->user()->id),
            'Zaproszenie do organizacji zostało odrzucone',
        );
    }

    public function acceptSeason(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->seasonInvitationService->accept($invitation, $this->user()->id),
            'Zaproszenie do sezonu zostało zaakceptowane',
        );
    }

    public function rejectSeason(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->seasonInvitationService->reject($invitation, $this->user()->id),
            'Zaproszenie do sezonu zostało odrzucone',
        );
    }

    public function acceptLeague(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->leagueInvitationService->accept($invitation, $this->user()->id),
            'Zaproszenie do ligi zostało zaakceptowane',
        );
    }

    public function rejectLeague(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->leagueInvitationService->reject($invitation, $this->user()->id),
            'Zaproszenie do ligi zostało odrzucone',
        );
    }

    public function joinQuickGame(int $lobby): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->quickGameLobbyService->joinById($lobby, $this->user()->id),
            'Dołączono do lobby. Rozgrywkę kontynuujesz w aplikacji.',
        );
    }

    public function rejectQuickGame(int $invitation): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->quickGameLobbyService->rejectInvitation($invitation, $this->user()->id),
            'Zaproszenie odrzucone',
        );
    }

    public function acceptLeagueGame(int $leagueGame): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->leagueGamePlayService->accept($this->user(), $leagueGame),
            'Zaakceptowano mecz ligowy. Rozgrywkę kontynuujesz w aplikacji.',
        );
    }

    public function rejectLeagueGame(int $leagueGame): RedirectResponse
    {
        return $this->attempt(
            fn () => $this->leagueGamePlayService->reject($this->user(), $leagueGame),
            'Zaproszenie do meczu odrzucone.',
        );
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }

    private function user(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
