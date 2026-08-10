<?php

namespace App\Http\Middleware;

use App\Models\Users\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isBanned()) {
            return $this->reject($request, $user);
        }

        return $next($request);
    }

    private function reject(Request $request, User $user): Response
    {
        $message = 'Konto zostało zablokowane.';

        if ($request->is('api/*') || $request->expectsJson()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => $message], 403);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('pages.loginPanel')
            ->with('error', $message);
    }
}
