<?php

namespace App\Http\Controllers;

use App\Enums\MatchKind;
use App\Services\Match\MatchDetailService;
use App\Services\Match\MatchScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function __construct(
        private MatchDetailService $matchDetailService,
        private MatchScoringService $matchScoringService,
    ) {
    }

    public function show(string $type, int $id): View
    {
        $detail = $this->matchDetailService->build(
            MatchDetailService::kindFromRoute($type),
            $id,
        );

        return view('matches.show', $detail);
    }

    public function live(string $type, int $id): View
    {
        $kind = MatchDetailService::kindFromRoute($type);
        $detail = $this->matchDetailService->build($kind, $id);
        [$context, $match] = $this->resolveScoringMatch($kind, $id);
        $initialState = $this->matchScoringService->getState($context, $match);

        return view('matches.live', array_merge($detail, [
            'initialState' => $initialState,
            'liveStateUrl' => route('matches.live.state', ['type' => $type, 'id' => $id]),
            'reverb' => $this->reverbClientConfig(),
        ]));
    }

    public function liveState(string $type, int $id): JsonResponse
    {
        $kind = MatchDetailService::kindFromRoute($type);
        [$context, $match] = $this->resolveScoringMatch($kind, $id);

        return response()->json($this->matchScoringService->getState($context, $match));
    }

    /**
     * @return array{0: \App\Support\Match\MatchContext, 1: \Illuminate\Database\Eloquent\Model}
     */
    private function resolveScoringMatch(MatchKind $kind, int $id): array
    {
        return match ($kind) {
            MatchKind::GROUP => $this->matchScoringService->resolveGroupGame($id),
            MatchKind::PLAYOFF => $this->matchScoringService->resolvePlayoffGame($id),
            MatchKind::QUICK => $this->matchScoringService->resolveQuickGame($id),
        };
    }

    /**
     * @return array{key: string, host: string, port: int, scheme: string}
     */
    private function reverbClientConfig(): array
    {
        return [
            'key' => (string) config('broadcasting.connections.reverb.key'),
            'host' => (string) env('REVERB_HOST', '127.0.0.1'),
            'port' => (int) env('REVERB_PORT', 8080),
            'scheme' => (string) env('REVERB_SCHEME', 'http'),
        ];
    }
}
