<?php

namespace App\Http\Controllers\Api;

use App\Services\Auth\AccountAuthException;
use App\Services\Auth\AccountAuthService;
use App\Services\Auth\MobileAppTokenService;
use App\Services\Auth\PasswordChangeService;
use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController
{

    public function __construct(
        private UserRegistrationService $registrationService,
        private AccountAuthService $accountAuthService,
        private MobileAppTokenService $mobileAppTokenService,
        private PasswordChangeService $passwordChangeService,
    ) {
    }

    public function tournamentLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
           'code' => 'required|string',
        ]);

        try {
            $payload = $this->accountAuthService->tournamentLogin($validated['code']);
        } catch (AccountAuthException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->statusCode);
        }

        return response()->json([
            'token' => $payload['token'],
            'tournamentId' => $payload['tournamentId'],
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

        $this->accountAuthService->resendVerificationEmail($validated['email']);

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

        try {
            $payload = $this->accountAuthService->loginApi(
                $validated['email'],
                $validated['password'],
            );
        } catch (AccountAuthException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->statusCode);
        }

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
        } catch (AccountAuthException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statusCode);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'token' => $payload['token'],
            'user' => $payload['user'],
        ]);
    }

    /**
     * Zmiana hasła konta gracza (aktualne + nowe + potwierdzenie).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $this->passwordChangeService->change($request->user(), $request->all());

        return response()->json([
            'message' => 'Hasło zostało zmienione.',
        ]);
    }
}
