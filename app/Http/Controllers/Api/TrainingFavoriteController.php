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
        ]);

        $exerciseKey = $request->input('exerciseKey');
        $result = is_string($exerciseKey) && $exerciseKey !== ''
            ? $this->favorites->addExercise($this->user($request), $exerciseKey)
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
                'exerciseKeys' => ['required', 'array', 'min:1', 'max:200'],
                'exerciseKeys.*' => ['string', 'distinct', 'max:64'],
            ]);
            $items = $this->favorites->reorderExercises($this->user($request), $request->input('exerciseKeys'));

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
        return response()->json($this->favorites->removeExercise($this->user($request), $exercise));
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
