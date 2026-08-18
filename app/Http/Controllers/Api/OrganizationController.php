<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\MapsIndexPageForApi;
use App\Models\Organization\Organization;
use App\Services\Competition\CompetitionShowSerializer;
use App\Services\Organization\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController
{
    use MapsIndexPageForApi;

    public function __construct(
        private OrganizationService $organizationService,
        private CompetitionShowSerializer $showSerializer,
    ) {
    }

    /**
     * GET /api/organizations?page=
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        return response()->json(
            $this->indexPageWithoutUrls($this->organizationService->getIndexPage($page)),
        );
    }

    /**
     * GET /api/organizations/{organization}
     */
    public function show(Organization $organization): JsonResponse
    {
        return response()->json($this->showSerializer->organization($organization));
    }
}
