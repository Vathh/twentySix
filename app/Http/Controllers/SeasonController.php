<?php

namespace App\Http\Controllers;

use App\Domain\Retention\ApplicationEntityKind;
use App\Domain\SeasonDomain;
use App\Models\Season\Season;
use App\Services\Organization\OrganizationService;
use App\Services\Retention\ApplicationEntityDeletionService;
use App\Services\Season\SeasonService;
use App\Services\Season\SeasonStatsService;
use App\Support\Catalog\CatalogSort;
use App\Support\Catalog\SeasonCatalogStatus;
use App\Support\Retention\ConfirmedEntityDeletion;
use App\Support\Text\PolishFold;
use App\Services\User\UserService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SeasonController extends Controller
{
    public function __construct(
        private SeasonService $seasonService,
        private OrganizationService $organizationService,
        private UserService $userService,
        private SeasonStatsService $seasonStatsService,
        private ApplicationEntityDeletionService $applicationEntityDeletionService,
    ) {}

    public function index(Request $request): Factory|View|JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $search = PolishFold::term($request->query('q'));
        $sort = CatalogSort::from($request->query('sort'));
        $status = SeasonCatalogStatus::from($request->query('status'));
        $data = $this->seasonService->getIndexPage($page, $search, $sort, $status);

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('seasons.index', [
            'items' => $data['items'],
            'hasMore' => $data['has_more'],
            'query' => $search ?? '',
            'summary' => $data['summary'],
            'page' => $page,
            'sort' => $sort,
            'status' => $status ?? '',
        ]);
    }

    public function create(Request $request): Factory|View
    {
        $organizationId = $request->query('organizationId');
        $this->organizationService->authorizeCreateSeason((int) $organizationId);

        return view('seasons.create', ['organizationId' => $organizationId]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'seasonName' => 'required|string|max:25|unique:seasons,name',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
        ]);

        $organizationId = $request->query('organizationId');

        $this->seasonService->create($organizationId, $validated['seasonName'], (array) Auth::id(), $validated['startDate'], $validated['endDate']);

        return redirect()
            ->route('organizations.show', ['organization' => $organizationId])
            ->with('success', 'Pomyślnie stworzono sezon!');
    }

    public function show(Season $season)
    {
        $season->loadMissing(['admins', 'organization.admins.player', 'tournaments']);
        $season->organization?->loadCount(['relatedUsers', 'guests']);
        $seasonDomain = SeasonDomain::fromEloquent($season, ['admins', 'organization', 'tournaments']);

        return view('seasons.show', [
            'season' => $seasonDomain,
            'standings' => $this->seasonStatsService->getStandings($season->id),
        ]);
    }

    public function edit(Season $season)
    {
        $this->authorize('update', $season);

        $season = SeasonDomain::fromEloquent($season);

        return view('seasons.edit', ['season' => $season]);
    }

    public function update(Request $request, Season $season)
    {
        $this->authorize('update', $season);

        //
    }

    public function destroy(Request $request, Season $season): RedirectResponse
    {
        $this->authorize('delete', $season);
        ConfirmedEntityDeletion::validate($request, $season->name);
        $organizationId = $season->organization_id;
        $this->applicationEntityDeletionService->hide(
            ApplicationEntityKind::Season,
            $season->id,
            (int) Auth::id(),
        );

        return redirect()
            ->route('organizations.show', $organizationId)
            ->with('success', 'Sezon został usunięty. Przez 90 dni może go przywrócić operator platformy.');
    }

    public function admins(int $seasonId): Factory|View
    {
        $season = $this->loadAndAuthorize($seasonId, ['organization']);
        $admins = $season->admins;
        $relatedUsers = $this->seasonService->getRelatedUsers($seasonId)
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->player->name])
            ->toArray();
        $filteredRelatedUsers = $this->userService->sortByNameAndRejectAdmins($relatedUsers, $season->admins);

        return view('seasons.admins', [
            'season' => $season,
            'admins' => $admins,
            'relatedUsers' => $filteredRelatedUsers,
        ]);
    }

    public function addAdmin(Request $request, int $seasonId)
    {
        $this->loadAndAuthorize($seasonId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->seasonService->addAdmin($seasonId, $validated['user_id']);

        return redirect()
            ->route('seasons.admins', $seasonId)
            ->with('success', 'Uprawnienie administratora nadano pomyślnie');
    }

    public function removeAdmin(Request $request, int $seasonId)
    {
        $this->loadAndAuthorize($seasonId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->seasonService->removeAdmin($seasonId, $validated['user_id']);

        return redirect()
            ->route('seasons.admins', $seasonId)
            ->with('success', 'Uprawnienie administratora usunięto pomyślnie');
    }

    public function loadAndAuthorize(int $seasonId, array $additionalRelations = []): SeasonDomain
    {
        return $this->seasonService->loadAndAuthorize($seasonId, $additionalRelations);
    }
}
