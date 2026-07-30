<?php

namespace App\Http\Controllers;

use App\Services\Auth\PasswordChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private PasswordChangeService $passwordChangeService)
    {
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('settings.password.edit');
    }

    public function editPassword(): View
    {
        return view('settings.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $this->passwordChangeService->change(Auth::user(), $request->all());

        return redirect()
            ->route('settings.password.edit')
            ->with('success', 'Hasło zostało zmienione.');
    }
}
