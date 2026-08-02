<?php

namespace App\Http\Controllers;

use App\Services\Auth\AccountAuthService;
use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{

    public function __construct(
        private UserRegistrationService $registrationService,
        private AccountAuthService $accountAuthService,
    ) {
    }

    public function register(Request $request)
    {
        $user = $this->registrationService->register(
            $request->all(),
            requirePasswordConfirmation: true,
        );

        return redirect()
            ->route('verification.notice')
            ->with('registered_email', $user->email)
            ->with('success', 'Konto utworzone. Sprawdź email i kliknij link potwierdzający, aby się zalogować.');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $this->accountAuthService->attemptWebLogin(
            $validated['email'],
            $validated['password'],
        );

        $request->session()->regenerate();

        return redirect()->route('pages.home');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pages.loginPanel');
    }
}
