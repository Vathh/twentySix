<?php

namespace App\Http\Controllers\Api;

use App\Domain\GameScoring\MatchFormat;
use App\Domain\Retention\ApplicationEntityKind;
use App\Enums\AssignableEntityType;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Services\Organization\OrganizationInvitationService;
use App\Services\Organization\OrganizationLogoService;
use App\Services\Organization\OrganizationService;
use App\Services\Player\PlayerService;
use App\Services\Retention\ApplicationEntityDeletionService;
use App\Services\User\UserService;
use App\Support\Media\SquareJpeg;
use App\Support\Organization\OrganizationMatchFormatPresets;
use App\Support\Retention\ConfirmedEntityDeletion;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrganizationManageController
{
    public function __construct(
        private OrganizationService $organizationService,
        private OrganizationInvitationService $invitationService,
        private OrganizationLogoService $logoService,
        private PlayerService $playerService,
        private UserService $userService,
        private ApplicationEntityDeletionService $deletionService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Organization::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name',
            'description' => 'nullable|string|max:500',
        ]);

        $organization = $this->organizationService->create(
            $validated['name'],
            $validated['description'] ?? null,
            (int) $request->user()->id,
        );

        return response()->json([
            'id' => $organization->id,
            'message' => 'Pomyślnie stworzono organizację!',
        ], 201);
    }

    public function manage(Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);

        return response()->json($this->payload($organization));
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organizations', 'name')->ignore($organization->id),
            ],
            'description' => 'nullable|string|max:500',
            'matchFormats' => 'nullable|array',
            'consolationMatchFormats' => 'nullable|array',
            'removeLogo' => 'sometimes|boolean',
        ]);

        $presets = null;
        if (is_array($validated['matchFormats'] ?? null) || is_array($validated['consolationMatchFormats'] ?? null)) {
            $stored = is_array($organization->match_format_presets) ? $organization->match_format_presets : null;
            $main = is_array($validated['matchFormats'] ?? null)
                ? OrganizationMatchFormatPresets::fromFormInput($validated['matchFormats'])
                : OrganizationMatchFormatPresets::forEditForm($stored);
            $presets = OrganizationMatchFormatPresets::mergeConsolation(
                $stored,
                $main,
                is_array($validated['consolationMatchFormats'] ?? null)
                    ? OrganizationMatchFormatPresets::consolationFromFormInput($validated['consolationMatchFormats'])
                    : null,
            );
        }

        $this->organizationService->update(
            $organization->id,
            $validated['name'],
            $validated['description'] ?? null,
            $presets,
        );

        if ($request->boolean('removeLogo')) {
            $this->logoService->remove($organization->fresh());
        }

        return response()->json(array_merge(
            ['message' => 'Pomyślnie zaktualizowano organizację'],
            $this->payload($organization->fresh()),
        ));
    }

    public function storeLogo(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);

        $request->validate([
            'logo' => 'required|file',
        ]);

        $logo = $request->file('logo');
        SquareJpeg::assertImage($logo, 'logo');
        $this->logoService->store($organization, $logo);

        return response()->json(array_merge(
            ['message' => 'Zapisano logo'],
            $this->payload($organization->fresh()),
        ));
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('delete', $organization);
        ConfirmedEntityDeletion::validate($request, $organization->name);
        $this->deletionService->hide(
            ApplicationEntityKind::Organization,
            $organization->id,
            (int) $request->user()->id,
        );

        return response()->json([
            'message' => 'Organizacja została usunięta. Przez 90 dni może ją przywrócić operator platformy.',
        ]);
    }

    public function searchUsers(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);
        $organization->load(['relatedUsers']);
        $pending = $this->invitationService->getPendingForOrganization($organization->id);
        $exclude = $organization->relatedUsers
            ->map(fn ($user) => ['id' => $user->id])
            ->concat($pending->map(fn ($invitation) => ['id' => $invitation->userId]));

        try {
            $users = $this->userService->search($exclude, $request->query('q'));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Nieprawidłowe wyszukiwanie.',
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

    public function invite(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        try {
            $result = $this->invitationService->send(
                $organization->id,
                (int) $validated['userId'],
                (int) $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $message = $result->joinedImmediately()
            ? \App\Domain\RelatedRosterInvite::SELF_JOIN_MESSAGE
            : 'Wysłano zaproszenie do organizacji';

        return response()->json(array_merge(
            ['message' => $message],
            $this->payload($organization->fresh()),
        ));
    }

    public function cancelInvitation(Organization $organization, int $invitation): JsonResponse
    {
        Gate::authorize('update', $organization);

        try {
            $this->invitationService->cancel($organization->id, $invitation);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(
            ['message' => 'Anulowano zaproszenie'],
            $this->payload($organization->fresh()),
        ));
    }

    public function removeMember(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        try {
            $this->invitationService->removeMember($organization->id, (int) $validated['userId']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(
            ['message' => 'Użytkownik usunięty z organizacji'],
            $this->payload($organization->fresh()),
        ));
    }

    public function addGuest(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:20',
                new \App\Rules\UniquePlayerNameInOrganization($organization->id),
            ],
        ]);

        $this->playerService->createGuest(
            $validated['name'],
            $organization->id,
            AssignableEntityType::ORGANIZATION,
        );

        return response()->json(array_merge(
            ['message' => 'Pomyślnie dodano gościa'],
            $this->payload($organization->fresh()),
        ));
    }

    public function removeGuest(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
        ]);

        $this->playerService->removeGuest(
            (int) $validated['playerId'],
            AssignableEntityType::ORGANIZATION,
            $organization->id,
        );

        return response()->json(array_merge(
            ['message' => 'Gość został usunięty'],
            $this->payload($organization->fresh()),
        ));
    }

    public function addAdmin(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        $this->organizationService->addAdmin($organization->id, (int) $validated['userId']);

        return response()->json(array_merge(
            ['message' => 'Uprawnienie administratora nadano pomyślnie'],
            $this->payload($organization->fresh()),
        ));
    }

    public function removeAdmin(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
        ]);

        try {
            $this->organizationService->removeAdmin($organization->id, (int) $validated['userId']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(
            ['message' => 'Uprawnienie administratora usunięto pomyślnie'],
            $this->payload($organization->fresh()),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Organization $organization): array
    {
        $organization->load(['admins.player', 'relatedUsers.player', 'guests']);
        $pending = $this->invitationService->getPendingForOrganization($organization->id);
        $admins = $this->userNames($organization->admins);
        $adminIds = array_column($admins, 'id');
        $related = $this->userNames($organization->relatedUsers);
        $presets = is_array($organization->match_format_presets) ? $organization->match_format_presets : [];

        return [
            'organization' => [
                'id' => (int) $organization->id,
                'name' => $organization->name,
                'description' => $organization->description ?? '',
                'logoUrl' => $organization->logoUrl(),
                'initials' => Player::initialsFromName($organization->name),
            ],
            'matchFormatStages' => OrganizationMatchFormatPresets::stageOptions(),
            'matchFormats' => OrganizationMatchFormatPresets::forEditForm($presets),
            'consolationMatchFormatStages' => OrganizationMatchFormatPresets::consolationStageOptions(),
            'consolationMatchFormats' => OrganizationMatchFormatPresets::defaultsForConsolation($presets),
            'startingScoreOptions' => MatchFormat::ALLOWED_STARTING_SCORES,
            'admins' => $admins,
            'canRemoveAdmin' => count($admins) > 1,
            'relatedUsers' => $related,
            'adminCandidates' => array_values(array_filter(
                $related,
                fn (array $person) => ! in_array($person['id'], $adminIds, true),
            )),
            'pendingInvitations' => $pending->map(fn ($invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->userPlayer?->name ?? '—',
            ])->values()->all(),
            'guests' => $organization->guests
                ->map(fn ($guest) => [
                    'id' => (int) $guest->id,
                    'name' => (string) $guest->name,
                ])
                ->sortBy(fn (array $row) => mb_strtolower($row['name']))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Users\User>  $users
     * @return list<array{id: int, name: string}>
     */
    private function userNames($users): array
    {
        return $users
            ->map(fn ($user) => [
                'id' => (int) $user->id,
                'playerId' => $user->player?->id !== null ? (int) $user->player->id : null,
                'name' => $user->player?->name ?? '—',
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values()
            ->all();
    }
}
