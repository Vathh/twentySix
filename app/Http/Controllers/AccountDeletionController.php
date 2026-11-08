<?php

namespace App\Http\Controllers;

use App\Domain\Auth\SoleOrganizationAdmin;
use App\Services\Auth\AccountDeletionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountDeletionController extends Controller
{
    public function __construct(private AccountDeletionService $deletion) {}

    public function create(): View
    {
        return view('pages.delete-account');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $currentId = Auth::id();

        try {
            $deletedId = $this->deletion->deleteUsingCredentials($data['email'], $data['password']);
        } catch (SoleOrganizationAdmin $exception) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', $exception->getMessage());
        }

        if ($currentId !== null && (int) $currentId === $deletedId) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('pages.loginPanel')
                ->with('success', 'Konto zostało usunięte.');
        }

        return back()->with('success', 'Konto zostało usunięte.');
    }
}
