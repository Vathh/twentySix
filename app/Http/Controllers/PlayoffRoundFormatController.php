<?php

namespace App\Http\Controllers;

use App\Enums\BracketSide;
use App\Models\Tournament\Tournament;
use App\Services\PlayoffGame\PlayoffRoundFormatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlayoffRoundFormatController extends Controller
{
    public function __construct(
        private PlayoffRoundFormatService $formatService,
    ) {}

    public function edit(Tournament $tournament): View
    {
        $described = $this->formatService->describe($tournament->id);

        return view('tournaments.playoff-formats', [
            'tournament' => $described['tournament'],
            'sides' => $described['sides'],
        ]);
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $result = $this->apply($request, $tournament);

        if ($result['applied'] === []) {
            return back()->with('error', $this->rejectedMessage($result['rejected']));
        }

        $message = 'Zapisano dystans rund.';
        if ($result['rejected'] !== []) {
            $message .= ' '.$this->rejectedMessage($result['rejected']);
        }

        return back()->with('success', $message);
    }

    public function showApi(Tournament $tournament): JsonResponse
    {
        $described = $this->formatService->describe($tournament->id);

        return response()->json([
            'sides' => array_map(fn (array $block) => [
                'side' => $block['side']->value,
                'label' => $block['label'],
                'stages' => $block['stages'],
            ], $described['sides']),
        ]);
    }

    public function updateApi(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            $result = $this->apply($request, $tournament);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => $result['applied'] === []
                ? $this->rejectedMessage($result['rejected'])
                : 'Zapisano dystans rund.',
            'applied' => $result['applied'],
            'rejected' => $result['rejected'],
        ], $result['applied'] === [] ? 422 : 200);
    }

    /**
     * @return array{applied: list<string>, rejected: list<array{stage: string, message: string}>}
     */
    private function apply(Request $request, Tournament $tournament): array
    {
        $validated = $request->validate([
            'bracketSide' => ['required', Rule::enum(BracketSide::class)],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.legsToWinSet' => ['required', 'integer', 'between:1,15'],
            'stages.*.setsToWinMatch' => ['required', 'integer', 'between:1,5'],
        ]);

        return $this->formatService->update(
            $tournament->id,
            BracketSide::from($validated['bracketSide']),
            $validated['stages'],
        );
    }

    /**
     * @param  list<array{stage: string, message: string}>  $rejected
     */
    private function rejectedMessage(array $rejected): string
    {
        if ($rejected === []) {
            return 'Żaden szczebel nie został zmieniony.';
        }

        return collect($rejected)->pluck('message')->implode(' ');
    }
}
