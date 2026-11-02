<?php

namespace App\Http\Controllers\Api;

use App\Domain\Retention\ApplicationEntityKind;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Retention\ApplicationEntityDeletionService;
use App\Services\Tournament\LoginCodeService;
use App\Services\Tournament\TournamentCancelService;
use App\Services\Tournament\TournamentGuestParticipantService;
use App\Services\Tournament\TournamentInvitationService;
use App\Services\Tournament\TournamentJoinRequestService;
use App\Services\Tournament\TournamentService;
use App\Services\Tournament\TournamentStartPageService;
use App\Services\User\UserService;
use App\Support\Retention\ConfirmedEntityDeletion;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TournamentManageController
{
    public function __construct(
        private TournamentService $tournamentService,
        private TournamentStartPageService $startPageService,
        private TournamentInvitationService $invitationService,
        private TournamentGuestParticipantService $guestParticipantService,
        private TournamentJoinRequestService $joinRequestService,
        private LoginCodeService $loginCodeService,
        private UserService $userService,
        private TournamentCancelService $cancelService,
        private ApplicationEntityDeletionService $deletionService,
    ) {}

    public function storeForOrganization(Request $request, Organization $organization): JsonResponse
    {
        $this->tournamentService->assertCanCreate(null, $organization->id);

        $validated = $request->validate([
            'name' => 'required|string|max:25',
            'date' => 'required|date',
        ]);

        $id = $this->tournamentService->create(
            null,
            $validated['name'],
            $validated['date'],
            (int) $request->user()->id,
            $organization->id,
        );

        return response()->json(['id' => $id], 201);
    }

    public function storeForSeason(Request $request, Season $season): JsonResponse
    {
        $this->tournamentService->assertCanCreate($season->id, null);

        $validated = $request->validate([
            'name' => 'required|string|max:25',
            'date' => 'required|date',
        ]);

        $id = $this->tournamentService->create(
            $season->id,
            $validated['name'],
            $validated['date'],
            (int) $request->user()->id,
            null,
        );

        return response()->json(['id' => $id], 201);
    }

    public function setup(int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);

        return response()->json($this->startPageService->apiSetup($tournament));
    }

    public function searchUsers(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $search = trim((string) $request->query('q', ''));

        try {
            $users = $this->userService->searchForTournamentInvitations(
                $search,
                $this->invitationService->getActiveInvitedUserIds($tournament),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'users' => [],
            ], 422);
        }

        return response()->json([
            'users' => $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->player?->name ?? '—',
            ])->values()->all(),
        ]);
    }

    public function invite(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        return $this->rosterAction(
            fn () => $this->invitationService->send($tournament, (int) $validated['userId'], (int) $request->user()->id),
            $tournament,
        );
    }

    public function inviteBulk(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $validated = $request->validate([
            'userIds' => 'required|array|min:1|max:100',
            'userIds.*' => 'integer|exists:users,id',
        ]);

        $result = $this->invitationService->sendBulk(
            $tournament,
            $validated['userIds'],
            (int) $request->user()->id,
        );

        return response()->json([
            ...$this->startPageService->apiSetup($tournament),
            'message' => sprintf(
                'Wysłano %d zaproszeń%s',
                $result['sent'],
                $result['skipped'] > 0 ? sprintf(' (%d pominięto)', $result['skipped']) : '',
            ),
        ]);
    }

    public function regenerateJoinCode(int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $model = $this->tournamentService->getModel($tournament);

        try {
            $this->joinRequestService->regenerateJoinCode($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->startPageService->apiSetup($tournament));
    }

    public function toggleJoinCode(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        try {
            $this->joinRequestService->toggleJoinCode(
                $this->tournamentService->getModel($tournament),
                (bool) $validated['enabled'],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->startPageService->apiSetup($tournament));
    }

    public function joinSelf(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);

        return $this->rosterAction(
            fn () => $this->invitationService->joinSelf($tournament, (int) $request->user()->id),
            $tournament,
        );
    }

    public function cancelInvitation(Request $request, int $tournament, int $invitation): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);

        return $this->rosterAction(
            fn () => $this->invitationService->cancel($tournament, $invitation),
            $tournament,
        );
    }

    public function removeParticipant(Request $request, int $tournament, int $invitation): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);

        return $this->rosterAction(
            fn () => $this->invitationService->removeParticipant($tournament, $invitation),
            $tournament,
        );
    }

    public function addGuest(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $validated = $request->validate([
            'name' => 'required|string|max:40',
        ]);

        return $this->rosterAction(
            fn () => $this->guestParticipantService->createAndAdd($tournament, $validated['name']),
            $tournament,
        );
    }

    public function addRelatedGuest(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $model = $this->tournamentService->getModel($tournament);
        if ($model->season_id === null) {
            return response()->json(['message' => 'Ten turniej nie ma gości sezonu.'], 422);
        }

        $validated = $request->validate([
            'playerId' => 'required|integer',
        ]);

        return $this->rosterAction(
            fn () => $this->guestParticipantService->addFromRelatedPool(
                $tournament,
                (int) $validated['playerId'],
                (int) $model->season_id,
            ),
            $tournament,
        );
    }

    public function removeGuest(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $validated = $request->validate([
            'playerId' => 'required|integer',
        ]);

        return $this->rosterAction(
            fn () => $this->guestParticipantService->remove($tournament, (int) $validated['playerId']),
            $tournament,
        );
    }

    public function approveJoinRequest(Request $request, int $tournament, int $joinRequest): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);

        return $this->rosterAction(
            fn () => $this->joinRequestService->approve($tournament, $joinRequest, (int) $request->user()->id),
            $tournament,
        );
    }

    public function rejectJoinRequest(Request $request, int $tournament, int $joinRequest): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);

        return $this->rosterAction(
            fn () => $this->joinRequestService->reject($tournament, $joinRequest, (int) $request->user()->id),
            $tournament,
        );
    }

    public function applyJoin(Request $request, int $tournament): JsonResponse
    {
        $model = Tournament::query()->findOrFail($tournament);
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Zaloguj się, aby się zgłosić.'], 401);
        }

        try {
            $joinRequest = $this->joinRequestService->applyToTournament($model, $user);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Zgłoszenie wysłane — czekaj na akceptację organizatora.',
            'requestId' => $joinRequest->id,
            'join' => $this->joinRequestService->statusForUser($model->fresh() ?? $model, $user->id),
        ]);
    }

    public function start(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);

        $validated = $request->validate([
            'tournamentFormat' => ['required', 'string', 'in:groups_playoff,single_elimination,double_elimination'],
            'groupsCount' => ['required_if:tournamentFormat,groups_playoff', 'nullable', 'integer', 'min:2'],
            'playoffBracketSize' => ['required_if:tournamentFormat,groups_playoff', 'nullable', 'integer', 'min:4'],
            'grandFinalMode' => ['required_if:tournamentFormat,double_elimination', 'nullable', 'string', 'in:single,reset'],
            'hasConsolationBracket' => ['sometimes', 'boolean'],
            'hasConsolationThirdPlace' => ['sometimes', 'boolean'],
            'matchFormats' => ['sometimes', 'array'],
            'consolationMatchFormats' => ['sometimes', 'array'],
        ]);

        try {
            $started = $this->tournamentService->runFromWeb($tournament, $validated, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! $started) {
            return response()->json(['message' => 'Turniej już wystartował'], 422);
        }

        $code = $this->loginCodeService->getCodeForTournament($tournament);

        return response()->json([
            'started' => true,
            'tabletLogin' => $code === null ? null : [
                'code' => $code,
                'url' => $this->loginCodeService->loginUrl($code),
            ],
        ]);
    }

    public function regenerateTabletCode(int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $code = $this->loginCodeService->regenerateForTournament($tournament);

        return response()->json([
            'code' => $code,
            'url' => $this->loginCodeService->loginUrl($code),
        ]);
    }

    public function admins(int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);

        return response()->json($this->adminPayload($tournament));
    }

    public function searchAdmins(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $search = trim((string) $request->query('q', ''));

        try {
            $users = $this->userService->searchForTournamentInvitations(
                $search,
                $this->tournamentService->getAdmins($tournament)->pluck('id'),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'users' => [],
            ], 422);
        }

        return response()->json([
            'users' => $users->map(fn ($user) => [
                'id' => (int) $user->id,
                'name' => $user->player?->name ?? '—',
            ])->values()->all(),
        ]);
    }

    public function addAdmin(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);
        $this->tournamentService->addAdmin($tournament, (int) $validated['userId']);

        return response()->json(array_merge(
            ['message' => 'Uprawnienie administratora nadano pomyślnie'],
            $this->adminPayload($tournament),
        ));
    }

    public function removeAdmin(Request $request, int $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament);
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        try {
            $this->tournamentService->removeAdmin($tournament, (int) $validated['userId']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(
            ['message' => 'Uprawnienie administratora usunięto pomyślnie'],
            $this->adminPayload($tournament),
        ));
    }

    public function cancel(Request $request, Tournament $tournament): JsonResponse
    {
        $this->tournamentService->loadAndAuthorize($tournament->id);
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Hasło jest nieprawidłowe.',
        ]);
        $this->cancelService->cancel($tournament->id);

        return response()->json([
            'message' => 'Rozgrywki anulowane. Możesz ułożyć skład i wystartować turniej od nowa.',
        ]);
    }

    public function destroy(Request $request, Tournament $tournament): JsonResponse
    {
        $tournament->loadMissing(['admins', 'season.admins']);
        $this->tournamentService->loadAndAuthorize($tournament->id);
        ConfirmedEntityDeletion::validate($request, $tournament->name);
        $seasonId = $tournament->season_id;
        $organizationId = $tournament->organization_id;
        $this->deletionService->hide(
            ApplicationEntityKind::Tournament,
            $tournament->id,
            (int) $request->user()->id,
        );

        return response()->json([
            'message' => 'Turniej został usunięty. Przez 90 dni może go przywrócić operator platformy.',
            'seasonId' => $seasonId !== null ? (int) $seasonId : null,
            'organizationId' => $organizationId !== null ? (int) $organizationId : null,
        ]);
    }

    /**
     * @return array{admins: list<array{id: int, name: string}>, canRemoveAdmin: bool, name: string}
     */
    private function adminPayload(int $tournamentId): array
    {
        $admins = $this->tournamentService->getAdmins($tournamentId)
            ->map(fn (array $admin) => [
                'id' => (int) $admin['id'],
                'name' => (string) $admin['name'],
            ])
            ->values()
            ->all();
        $tournament = Tournament::query()->findOrFail($tournamentId);

        return [
            'name' => $tournament->name,
            'admins' => $admins,
            'canRemoveAdmin' => count($admins) > 1,
        ];
    }

    private function rosterAction(callable $action, int $tournamentId): JsonResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->startPageService->apiSetup($tournamentId));
    }
}
