<?php

namespace App\Http\Controllers\Api;

use App\Models\Tournament\LoginCode;
use App\Models\Users\User;
use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController
{

    public function __construct(
        private UserRegistrationService $registrationService,
    ) {
    }

    public function tournamentLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
           'code' => 'required|string',
        ]);

        $loginCode = LoginCode::where('code', $validated['code'])->first();

        if (!$loginCode) {
            return response()->json([
                'message' => 'Nieprawidłowy kod logowania',
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
            'name' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = $this->registrationService->register($validated);

        return response()->json([
            'message' => 'Konto utworzone. Sprawdź email i kliknij link potwierdzający, aby się zalogować.',
            'email' => $user->email,
        ], 201);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Jeśli konto istnieje i nie jest potwierdzone, wysłaliśmy link aktywacyjny.',
        ]);
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

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Potwierdź adres email — sprawdź skrzynkę (link z rejestracji).',
            ], 403);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;
        $user->load('player');

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->player->name ?? null,
                'playerId' => $user->player?->id,
            ],
        ]);
    }
}
