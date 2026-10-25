<?php

namespace App\Http\Controllers;

use App\Domain\Auth\PasswordResetDomain;
use App\Services\Auth\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    public function __construct(
        private PasswordResetService $passwordResetService,
    ) {}

    public function create(): View
    {
        return view('pages.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $this->passwordResetService->requestLink($validated['email']);

        return back()->with('success', PasswordResetDomain::SENT_MESSAGE);
    }

    public function edit(Request $request, string $token): View
    {
        return view('pages.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string',
            'password_confirmation' => 'required|string',
        ]);

        $this->passwordResetService->reset(
            $validated['email'],
            $validated['token'],
            $validated['password'],
            $validated['password_confirmation'],
        );

        return redirect()
            ->route('pages.loginPanel')
            ->with('success', PasswordResetDomain::RESET_MESSAGE);
    }
}
