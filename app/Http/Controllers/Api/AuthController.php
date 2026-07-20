<?php

namespace App\Http\Controllers\Api;

use App\Enums\TournamentStatus;
use App\Models\Tournament\LoginCode;
use App\Models\Users\User;
use App\Services\Auth\MobileAppTokenService;
use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController
{

    public function __construct(
        private UserRegistrationService $registrationService,
        private MobileAppTokenService $mobileAppTokenService,
    ) {
    }

    public function tournamentLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
           'code' => 'required|string',
        ]);

        $loginCode = LoginCode::query()
            ->with('tournament')
            ->where('code', $validated['code'])
            ->first();

        if (! $loginCode || $loginCode->tournament === null) {
            return response()->json([
                'message' => 'Nieprawidłowy kod logowania',
            ], 401);
        }

        if ($loginCode->tournament->status === TournamentStatus::FINISHED) {
            return response()->json([
                'message' => 'Turniej zakończony — kody sędziowania są już nieważne.',
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

        $payload = $this->mobileAppTokenService->issueForUser($user);

        return response()->json([
            'token' => $payload['token'],
            'user' => $payload['user'],
        ]);
    }

    /**
     * Wylogowanie — unieważnia bieżący token Bearer (mobile).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $this->mobileAppTokenService->revokeCurrent($user);
        }

        return response()->json([
            'message' => 'Wylogowano.',
        ]);
    }

    /**
     * Odświeżenie sesji mobile — nowy token, ważność +TTL od teraz (sliding window).
     */
    public function refreshSession(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        if ($current === null) {
            return response()->json(['message' => 'Brak tokena.'], 401);
        }

        try {
            $payload = $this->mobileAppTokenService->refresh($user, $current);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'token' => $payload['token'],
            'user' => $payload['user'],
        ]);
    }
}
