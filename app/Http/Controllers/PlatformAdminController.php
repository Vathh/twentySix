<?php

namespace App\Http\Controllers;

use App\Domain\Retention\ApplicationEntityKind;
use App\Services\Moderation\ContentReportService;
use App\Services\Platform\PlatformAdminService;
use App\Services\Retention\ApplicationEntityDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    public function __construct(
        private PlatformAdminService $platformAdminService,
        private ApplicationEntityDeletionService $applicationEntityDeletionService,
        private ContentReportService $contentReports,
    ) {}

    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'stats' => $this->platformAdminService->dashboard(),
        ]);
    }

    public function users(Request $request): View
    {
        $search = $request->string('q')->toString();

        return view('admin.users', [
            'users' => $this->platformAdminService->listUsers($search !== '' ? $search : null),
            'search' => $search,
        ]);
    }

    public function showUser(int $userId): View
    {
        $detail = $this->platformAdminService->userDetail($userId);

        return view('admin.user', [
            'user' => $detail['user'],
            'activity' => $detail['activity'],
            'recentGames' => $detail['recentGames'],
        ]);
    }

    public function updateCanCreateOrganizations(Request $request, int $userId): RedirectResponse
    {
        $validated = $request->validate([
            'can_create_organizations' => ['required', 'boolean'],
        ]);

        $user = $this->platformAdminService->setCanCreateOrganizations(
            $userId,
            (bool) $validated['can_create_organizations'],
        );

        $label = $user->player?->name ?? $user->email;
        $state = $user->can_create_organizations ? 'włączone' : 'wyłączone';

        return back()->with('success', "Tworzenie organizacji dla {$label}: {$state}");
    }

    public function updateBanned(Request $request, int $userId): RedirectResponse
    {
        $validated = $request->validate([
            'banned' => ['required', 'boolean'],
        ]);

        try {
            $user = $this->platformAdminService->setBanned(
                $userId,
                (bool) $validated['banned'],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $label = $user->player?->name ?? $user->email;
        $state = $user->isBanned() ? 'zablokowane' : 'odblokowane';

        return back()->with('success', "Konto {$label}: {$state}");
    }

    public function reports(): View
    {
        return view('admin.reports', [
            'reports' => $this->contentReports->openReports(),
        ]);
    }

    public function dismissReport(int $report): RedirectResponse
    {
        return $this->reportAction($report, function (int $reportId) {
            $this->contentReports->dismiss($reportId);

            return 'Zgłoszenie odrzucone.';
        });
    }

    public function deleteReportedComment(int $report): RedirectResponse
    {
        return $this->reportAction($report, function (int $reportId) {
            $this->contentReports->deleteComment($reportId);

            return 'Komentarz został usunięty.';
        });
    }

    public function deleteReportedPost(int $report): RedirectResponse
    {
        return $this->reportAction($report, function (int $reportId) {
            $this->contentReports->deletePost($reportId);

            return 'Wpis został usunięty.';
        });
    }

    public function banReportedUser(int $report): RedirectResponse
    {
        return $this->reportAction($report, function (int $reportId) {
            $this->contentReports->ban($reportId);

            return 'Konto zostało zbanowane.';
        });
    }

    public function deleted(): View
    {
        return view('admin.deleted', [
            'entities' => $this->applicationEntityDeletionService->deletedRoots(),
        ]);
    }

    public function restore(string $kind, int $id): RedirectResponse
    {
        try {
            $entityKind = ApplicationEntityKind::fromRoute($kind);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        try {
            $name = $this->applicationEntityDeletionService->restore($entityKind, $id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Przywrócono: {$name}");
    }

    /**
     * @param  callable(int): string  $action
     */
    private function reportAction(int $reportId, callable $action): RedirectResponse
    {
        try {
            $message = $action($reportId);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $message);
    }
}
