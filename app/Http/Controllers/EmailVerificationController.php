<?php

namespace App\Http\Controllers;

use App\Models\Users\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
            abort(403);
        }

        if (! URL::hasValidSignature($request)) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()
                ->route('pages.loginPanel')
                ->with('success', 'Ten adres email jest już potwierdzony. Możesz się zalogować.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect()
            ->route('pages.loginPanel')
            ->with('success', 'Adres email potwierdzony. Możesz się zalogować.');
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()
            ->route('verification.notice')
            ->with('registered_email', $validated['email'])
            ->with('success', 'Jeśli konto istnieje i nie jest potwierdzone, wysłaliśmy link aktywacyjny.');
    }
}
