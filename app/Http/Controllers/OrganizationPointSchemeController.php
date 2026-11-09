<?php

namespace App\Http\Controllers;

use App\Services\Organization\OrganizationService;
use App\Services\PointScheme\OrganizationPointSchemeService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationPointSchemeController extends Controller
{
    public function __construct(
        private OrganizationService $organizationService,
        private OrganizationPointSchemeService $pointSchemeService,
    ) {}

    public function edit(int $organization): Factory|View
    {
        $organizationDomain = $this->organizationService->loadAndAuthorize($organization);

        return view('organizations.point-scheme', [
            'organization' => $organizationDomain,
            'bands' => $this->pointSchemeService->formState($organization),
            'matchesDefaults' => $this->pointSchemeService->matchesSystemDefaults($organization),
        ]);
    }

    public function update(Request $request, int $organization): RedirectResponse
    {
        $this->organizationService->loadAndAuthorize($organization);
        $this->pointSchemeService->save($organization, $request->input('bands'));

        return redirect()
            ->route('organizations.point-scheme.edit', $organization)
            ->with('success', 'Punktacja turniejowa zapisana. Wejdzie w turnieje, które wystartują po tym zapisie.');
    }

    public function reset(int $organization): RedirectResponse
    {
        $this->organizationService->loadAndAuthorize($organization);
        $this->pointSchemeService->resetToDefaults($organization);

        return redirect()
            ->route('organizations.point-scheme.edit', $organization)
            ->with('success', 'Przywrócono domyślną punktację. Wejdzie w turnieje, które wystartują po tym zapisie.');
    }
}
