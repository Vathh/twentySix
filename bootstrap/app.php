<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // Nie podawaj `channels` tutaj: wtedy framework woła Broadcast::routes(null) z samym `web`
        // (bez auth) i /broadcasting/auth zwraca 403 dla kanałów prywatnych z Bearerem z aplikacji.
        // Trasy + kanały rejestrujemy w AppServiceProvider.
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.'], 419);
            }

            return redirect()
                ->route('pages.loginPanel')
                ->with('error', 'Sesja wygasła. Zaloguj się ponownie.');
        });
    })->create();
