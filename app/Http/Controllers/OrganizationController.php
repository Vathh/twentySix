<?php

namespace App\Http\Controllers;

use App\Domain\OrganizationDomain;
use App\Enums\AssignableEntityType;
use App\Models\Organization\Organization;
use App\Models\Users\User;
use App\Services\League\LeagueService;
use App\Services\Organization\OrganizationService;
use App\Services\Player\PlayerService;
use App\Services\User\UserService;
use App\Domain\GameScoring\MatchFormat;
use App\Support\Organization\OrganizationMatchFormatPresets;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationService $organizationService,
        private UserService $userService,
        private PlayerService $playerService,
        private LeagueService $leagueService,
    ) {
        $this->authorizeResource(Organization::class, 'organization');
    }

    public function index(Request $request): Factory|View|JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $data = $this->organizationService->getIndexPage($page);

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('organizations.index', [
            'items' => $data['items'],
            'hasMore' => $data['has_more'],
        ]);
    }

    public function create(): Factory|View
    {
        return view('organizations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organizationName' => 'required|string|max:255|unique:organizations,name',
            'description' => 'string|max:500',
        ]);

        $this->organizationService->create($validated['organizationName'], $validated['description'], Auth::id());

        return redirect()
                    ->route('organizations.index')
                    ->with('success', 'Pomyślnie stworzono organizację!');
    }

    public function show(Organization $organization): Factory|View
    {
        $organization->loadMissing(['admins', 'seasons']);
        $organizationDomain = OrganizationDomain::fromEloquent($organization, ['admins', 'seasons']);
        $seasons = collect($organizationDomain->seasons)
            ->sortByDesc(fn($season) => $season->updatedAt)
            ->values();

        return view('organizations.show', [
            'organization' => $organizationDomain,
            'seasons' => $seasons,
            'leagues' => $this->leagueService->listForOrganization($organization->id),
        ]);
    }

    public function edit(Organization $organization): Factory|View
    {
        $organization->loadMissing(['admins']);
        $organizationDomain = OrganizationDomain::fromEloquent($organization, ['admins']);

        return view('organizations.edit', [
            'organization' => $organizationDomain,
            'startingScoreOptions' => MatchFormat::ALLOWED_STARTING_SCORES,
            'matchFormatStages' => OrganizationMatchFormatPresets::stageOptions(),
            'matchFormats' => old(
                'matchFormats',
                OrganizationMatchFormatPresets::forEditForm($organizationDomain->matchFormatPresets),
            ),
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'organizationName' => 'required|string|max:255',
            'description' => 'required|string|max:500',
            'matchFormats' => 'nullable|array',
        ]);

        $presets = OrganizationMatchFormatPresets::fromFormInput(
            is_array($validated['matchFormats'] ?? null) ? $validated['matchFormats'] : [],
        );

        $this->organizationService->update(
            $organization->id,
            $validated['organizationName'],
            $validated['description'],
            $presets,
        );

        return redirect()
            ->route('organizations.show', $organization->id)
            ->with('success', 'Pomyślnie zaktualizowano organizację');
    }

    public function relatedUsers(Request $request, int $organizationId): Factory|View
    {
        $organization = $this->loadAndAuthorize($organizationId, ['relatedUsers']);

        $search = $request->input('search');

        $users = $this->userService->search($organization->relatedUsers, $search);

        $relatedUsers = $this->userService->sortByName($organization->relatedUsers);

        return view('organizations.relatedUsers', [
            'organization' => $organization,
            'relatedUsers' => $relatedUsers,
            'users' => $users
        ]);
    }

    public function addRelatedUser(Request $request, int $organizationId)
    {
        $this->loadAndAuthorize($organizationId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->organizationService->addRelatedUser($organizationId, $validated['user_id']);

        return redirect()
                    ->route('organizations.relatedUsers', $organizationId)
                    ->with('success', 'Użytkownik dodany do organizacji');
    }

    public function removeRelatedUser(Request $request, int $organizationId)
    {
        $this->loadAndAuthorize($organizationId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->organizationService->removeRelatedUser($organizationId, $validated['user_id']);

        return redirect()
                    ->route('organizations.relatedUsers', $organizationId)
                    ->with('success', 'Użytkownik usunięty z organizacji');
    }

    public function admins(int $organizationId): Factory|View
    {
        $organization = $this->loadAndAuthorize($organizationId, ['relatedUsers']);
        $admins = $organization->admins;
        $relatedUsers = $this->userService->sortByNameAndRejectAdmins($organization->relatedUsers, $organization->admins);

        return view('organizations.admins', [
            'organization' => $organization,
            'admins' => $admins,
            'relatedUsers' => $relatedUsers
        ]);
    }

    public function addAdmin(Request $request, int $organizationId)
    {
        $this->loadAndAuthorize($organizationId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->organizationService->addAdmin($organizationId, $validated['user_id']);

        return redirect()
                    ->route('organizations.admins', $organizationId)
                    ->with('success', 'Uprawnienie administratora nadano pomyślnie');
    }

    public function removeAdmin(Request $request, int $organizationId)
    {
        $this->loadAndAuthorize($organizationId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->organizationService->removeAdmin($organizationId, $validated['user_id']);

        return redirect()
                    ->route('organizations.admins', $organizationId)
                    ->with('success', 'Uprawnienie administratora usunięto pomyślnie');
    }

    public function guests(int $organizationId): Factory|View
    {
        $organization = $this->loadAndAuthorize($organizationId, ['guests']);

        $guests = $this->userService->sortByName($organization->guests);

        return view('organizations.guests', [
            'organization' => $organization,
            'guests' => $guests
        ]);
    }

    public function addGuest(Request $request, int $organizationId)
    {
        $this->loadAndAuthorize($organizationId);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:20',
                new \App\Rules\UniquePlayerNameInOrganization($organizationId),
            ],
        ]);

        $this->playerService->createGuest($validated['name'], $organizationId, AssignableEntityType::ORGANIZATION);

        return redirect()
                    ->route('organizations.guests', $organizationId)
                    ->with('success', 'Pomyślnie dodano gościa');
    }

    public function removeGuest(Request $request, int $organizationId)
    {
        $this->loadAndAuthorize($organizationId);

        $validated = $request->validate([
            'player_id' => 'required|exists:players,id',
        ]);

        $this->playerService->removeGuest($validated['player_id']);

        return redirect()
            ->route('organizations.guests', $organizationId)
            ->with('success', 'Pomyślnie usunięto gościa');
    }

    public function loadAndAuthorize(int $organizationId, array $additionalRelations = []): OrganizationDomain
    {
        $allRelations = array_merge($additionalRelations, ['admins']);
        $organization = Organization::with($allRelations)->findOrFail($organizationId);
        $this->authorize('update', $organization);

        return OrganizationDomain::fromEloquent($organization, $allRelations);
    }
}










