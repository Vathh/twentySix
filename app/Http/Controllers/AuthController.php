<?php

namespace App\Http\Controllers;

use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    public function __construct(private PlayerService $playerService)
    {
    }

    public function register (Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create($validated);

        $this->playerService->create($validated['name'], $user->id);

        Auth::login($user);

        return redirect()
            ->route('pages.home')
            ->with('success', 'Pomyślnie dodano użytkownika!');
    }

    public function login (Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if(Auth::attempt($validated)){
            $request->session()->regenerate();

            return redirect()->route('pages.home');
        }

        throw ValidationException::withMessages([
            'credentials' => __('validation.auth.failed')
        ]);
    }

    public function logout (Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pages.loginPanel');
    }
}

