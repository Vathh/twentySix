<?php

namespace App\Http\Controllers\Api;

use App\Domain\Retention\ApplicationEntityKind;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Services\Organization\OrganizationService;
use App\Services\Retention\ApplicationEntityDeletionService;
use App\Services\Season\SeasonService;
use App\Support\Retention\ConfirmedEntityDeletion;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SeasonManageController
{
    public function __construct(
        private SeasonService $seasonService,
        private OrganizationService $organizationService,
        private ApplicationEntityDeletionService $deletionService,
    ) {}

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->organizationService->authorizeCreateSeason($organization->id);

        $validated = $request->validate([
            'name' => 'required|string|max:25|unique:seasons,name',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        $this->seasonService->create(
            $organization->id,
            $validated['name'],
            [(int) $request->user()->id],
            $validated['startDate'],
            $validated['endDate'],
        );

        $season = Season::query()
            ->where('organization_id', $organization->id)
            ->where('name', $validated['name'])
            ->first();

        return response()->json([
            'id' => $season?->id,
            'message' => 'Pomyślnie stworzono sezon!',
        ], 201);
    }

    public function manage(Season $season): JsonResponse
    {
        Gate::authorize('update', $season);

        return response()->json($this->payload($season));
    }

    public function update(Request $request, Season $season): JsonResponse
    {
        Gate::authorize('update', $season);

        $validated = $request->validate([
            'name' => 'required|string|max:25|unique:seasons,name,'.$season->id,
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        $this->seasonService->update(
            $season->id,
            $validated['name'],
            $validated['startDate'],
            $validated['endDate'],
        );

        return response()->json(array_merge(
            ['message' => 'Pomyślnie zaktualizowano sezon'],
            $this->payload($season->fresh()),
        ));
    }

    public function destroy(Request $request, Season $season): JsonResponse
    {
        Gate::authorize('delete', $season);
        ConfirmedEntityDeletion::validate($request, $season->name);
        $organizationId = $season->organization_id;
        $this->deletionService->hide(
            ApplicationEntityKind::Season,
            $season->id,
            (int) $request->user()->id,
        );

        return response()->json([
            'message' => 'Sezon został usunięty. Przez 90 dni może go przywrócić operator platformy.',
            'organizationId' => $organizationId,
        ]);
    }

    public function addAdmin(Request $request, Season $season): JsonResponse
    {
        Gate::authorize('update', $season);
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        $this->seasonService->addAdmin($season->id, (int) $validated['userId']);

        return response()->json(array_merge(
            ['message' => 'Uprawnienie administratora nadano pomyślnie'],
            $this->payload($season->fresh()),
        ));
    }

    public function removeAdmin(Request $request, Season $season): JsonResponse
    {
        Gate::authorize('update', $season);
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        try {
            $this->seasonService->removeAdmin($season->id, (int) $validated['userId']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(
            ['message' => 'Uprawnienie administratora usunięto pomyślnie'],
            $this->payload($season->fresh()),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Season $season): array
    {
        $season->load(['admins.player', 'organization.relatedUsers.player']);
        $admins = $this->userNames($season->admins);
        $adminIds = array_column($admins, 'id');
        $related = $this->userNames($season->organization?->relatedUsers ?? collect());

        return [
            'season' => [
                'id' => (int) $season->id,
                'name' => $season->name,
                'startDate' => $season->start_date?->format('Y-m-d'),
                'endDate' => $season->end_date?->format('Y-m-d'),
                'organizationId' => $season->organization_id !== null ? (int) $season->organization_id : null,
            ],
            'admins' => $admins,
            'canRemoveAdmin' => count($admins) > 1,
            'adminCandidates' => array_values(array_filter(
                $related,
                fn (array $person) => ! in_array($person['id'], $adminIds, true),
            )),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Users\User>  $users
     * @return list<array{id: int, name: string}>
     */
    private function userNames($users): array
    {
        return collect($users)
            ->map(fn ($user) => [
                'id' => (int) $user->id,
                'name' => $user->player?->name ?? '—',
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values()
            ->all();
    }
}
