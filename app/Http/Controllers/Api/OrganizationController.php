<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\MapsIndexPageForApi;
use App\Models\Organization\Organization;
use App\Services\Competition\CompetitionShowSerializer;
use App\Services\Organization\OrganizationJoinRequestService;
use App\Services\Organization\OrganizationService;
use App\Support\Catalog\CatalogSort;
use App\Support\Text\PolishFold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationController
{
    use MapsIndexPageForApi;

    public function __construct(
        private OrganizationService $organizationService,
        private CompetitionShowSerializer $showSerializer,
        private OrganizationJoinRequestService $joinRequests,
    ) {}

    /**
     * GET /api/organizations?page=&q=&sort=
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $search = PolishFold::term($request->query('q'));
        $sort = CatalogSort::from($request->query('sort'));

        $payload = $this->indexPageWithoutUrls($this->organizationService->getIndexPage($page, $search, $sort));
        $payload['canCreate'] = $request->user()?->can('create', Organization::class) ?? false;

        return response()->json($payload);
    }

    /**
     * GET /api/organizations/{organization}
     */
    public function show(Organization $organization): JsonResponse
    {
        $payload = $this->showSerializer->organization($organization);
        $payload['canManage'] = Gate::allows('update', $organization);
        $payload['join'] = $this->joinRequests->statusForUser($organization, request()->user());

        return response()->json($payload);
    }

    public function applyJoinRequest(Organization $organization): JsonResponse
    {
        try {
            $this->joinRequests->apply($organization, request()->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Zgłoszenie wysłane. Czekasz na decyzję organizatora.',
            'join' => $this->joinRequests->statusForUser($organization, request()->user()),
        ]);
    }
}
