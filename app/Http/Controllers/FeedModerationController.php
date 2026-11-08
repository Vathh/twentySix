<?php

namespace App\Http\Controllers;

use App\Domain\Moderation\ReportReason;
use App\Domain\Moderation\ReportSubjectType;
use App\Services\Moderation\ContentReportService;
use App\Services\Moderation\UserBlockService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedModerationController extends Controller
{
    public function __construct(
        private UserBlockService $blocks,
        private ContentReportService $reports,
    ) {}

    public function blocked(Request $request): JsonResponse|View
    {
        $people = $this->blocks->blockedPeople($request->user());

        if ($request->expectsJson()) {
            return response()->json(['blocked' => $people]);
        }

        return view('settings.blocks', ['blocked' => $people]);
    }

    public function block(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'integer'],
        ]);

        try {
            $this->blocks->block($request->user(), (int) $data['userId']);
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['blocked' => true]);
        }

        return back()->with('success', 'Gracz został zablokowany.');
    }

    public function unblock(Request $request): JsonResponse|RedirectResponse
    {
        $userId = $request->route('user') ?? $request->input('userId');
        if (! is_numeric($userId)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Wskaż gracza.'], 422);
            }

            return back()->with('error', 'Wskaż gracza.');
        }

        $this->blocks->unblock($request->user(), (int) $userId);

        if ($request->expectsJson()) {
            return response()->json(['blocked' => false]);
        }

        return back()->with('success', 'Gracz został odblokowany.');
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subjectType' => ['required', Rule::enum(ReportSubjectType::class)],
            'subjectId' => ['required', 'integer'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->json(function () use ($request, $data) {
            $this->reports->report(
                $request->user(),
                ReportSubjectType::from($data['subjectType']),
                (int) $data['subjectId'],
                ReportReason::from($data['reason']),
                $data['details'] ?? null,
            );

            return ['reported' => true];
        });
    }

    /**
     * @param  callable(): array<string, mixed>  $action
     */
    private function json(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
