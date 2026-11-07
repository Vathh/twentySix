<?php

namespace App\Http\Controllers;

use App\Services\Auth\AccountAuthService;
use App\Services\Auth\TermsAcceptanceService;
use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private UserRegistrationService $registrationService,
        private AccountAuthService $accountAuthService,
        private TermsAcceptanceService $termsAcceptanceService,
    ) {}

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
            'remember' => 'sometimes|boolean',
        ]);

        $this->accountAuthService->attemptWebLogin(
            $validated['email'],
            $validated['password'],
            $request->boolean('remember'),
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

    public function acceptTerms(Request $request)
    {
        $this->termsAcceptanceService->accept($request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'termsAccepted' => true,
            ]);
        }

        return back()->with('success', 'Zaakceptowano regulamin i politykę prywatności.');
    }
}
