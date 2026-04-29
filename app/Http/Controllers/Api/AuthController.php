<?php

namespace App\Http\Controllers\Api;

use App\Models\LoginCode;
use App\Models\User;
use App\Rules\UniquePlayerNameForRegistered;
use App\Services\Player\PlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController
{

    public function __construct(
        private PlayerService $playerService
    )
    {
    }

    public function tournamentLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
           'code' => 'required|string',
        ]);

        $loginCode = LoginCode::where('code', $validated['code'])->first();

        if (!$loginCode) {
            return response()->json([
                'message' => 'Nieprawidłowy kod logowania'
            ], 401);
        }

        $token = $loginCode->createToken('counter')->plainTextToken;

        return response()->json([
            'token' => $token,
            'tournamentId' => $loginCode->tournament_id,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:20',
                new UniquePlayerNameForRegistered(),
            ],
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'password' => $validated['password'],
            // can_create_leagues i role mają domyślne wartości z migracji (0 i 'user')
        ]);

        // Automatyczne utworzenie Player dla użytkownika
        $this->playerService->create($validated['name'], $user->id);

        // Utworzenie tokenu Sanctum
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Odświeżenie użytkownika z relacją player
        $user->load('player');

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->player->name ?? $validated['name'],
            ],
        ], 201);
    }

    /**
     * Logowanie na konto gracza (email + hasło). Zwraca token do użycia w API.
     * Używane w aplikacji mobilnej przy „Zaloguj się”.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|max:255',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Nieprawidłowy email lub hasło',
            ], 401);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;
        $user->load('player');

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->player->name ?? null,
            ],
        ]);
    }
}









