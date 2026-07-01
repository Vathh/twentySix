<?php

namespace App\Http\Controllers;

use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\Users\User;

class AuthController extends Controller
{

    public function __construct(private UserRegistrationService $registrationService)
    {
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

        $user = User::where('email', $validated['email'])->first();

        if ($user !== null
            && Hash::check($validated['password'], $user->password)
            && ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Potwierdź adres email — sprawdź skrzynkę (link z rejestracji).',
            ]);
        }

        if (Auth::attempt($validated)) {
            $request->session()->regenerate();

            return redirect()->route('pages.home');
        }

        throw ValidationException::withMessages([
            'credentials' => __('validation.auth.failed'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pages.loginPanel');
    }
}
