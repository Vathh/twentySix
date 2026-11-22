<?php

namespace App\Http\Controllers\Api;

use App\Models\Users\User;
use App\Services\Training\TrainingFavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingFavoriteController
{
    public function __construct(
        private TrainingFavoriteService $favorites,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->favorites->grouped($this->user($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'definitionId' => ['nullable', 'integer', 'min:1', 'required_without:exerciseKey', 'prohibits:exerciseKey'],
            'exerciseKey' => ['nullable', 'string', 'max:64', 'required_without:definitionId', 'prohibits:definitionId'],
            'variant' => ['nullable', 'string', 'max:32'],
        ]);

        $exerciseKey = $request->input('exerciseKey');
        $result = is_string($exerciseKey) && $exerciseKey !== ''
            ? $this->favorites->addExercise(
                $this->user($request),
                $exerciseKey,
                is_string($request->input('variant')) ? $request->input('variant') : null,
            )
            : $this->favorites->add($this->user($request), $request->integer('definitionId'));

        return response()->json($result, $result['created'] ? 201 : 200);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'kind' => ['required', 'string', Rule::in(['challenges', 'plans', 'exercises'])],
        ]);

        if ($request->input('kind') === 'exercises') {
            $request->validate([
                'items' => ['required_without:exerciseKeys', 'array', 'min:1', 'max:200'],
                'items.*.exerciseKey' => ['required_with:items', 'string', 'max:64'],
                'items.*.variant' => ['nullable', 'string', 'max:32'],
                'exerciseKeys' => ['required_without:items', 'array', 'min:1', 'max:200'],
                'exerciseKeys.*' => ['string', 'max:64'],
            ]);
            $refs = $request->input('items') ?? $request->input('exerciseKeys');
            $items = $this->favorites->reorderExercises($this->user($request), $refs);

            return response()->json(['items' => $items]);
        }

        $request->validate([
            'definitionIds' => ['required', 'array', 'min:1', 'max:200'],
            'definitionIds.*' => ['integer', 'distinct', 'min:1'],
        ]);

        $items = $this->favorites->reorder(
            $this->user($request),
            (string) $request->string('kind'),
            $request->input('definitionIds'),
        );

        return response()->json(['items' => $items]);
    }

    public function destroy(Request $request, int $definition): JsonResponse
    {
        return response()->json($this->favorites->remove($this->user($request), $definition));
    }

    public function destroyExercise(Request $request, string $exercise): JsonResponse
    {
        $request->validate([
            'variant' => ['nullable', 'string', 'max:32'],
        ]);

        return response()->json($this->favorites->removeExercise(
            $this->user($request),
            $exercise,
            is_string($request->query('variant')) ? $request->query('variant') : (
                is_string($request->input('variant')) ? $request->input('variant') : null
            ),
        ));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
