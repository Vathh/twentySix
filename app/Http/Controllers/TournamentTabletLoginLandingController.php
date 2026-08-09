<?php

namespace App\Http\Controllers;

use App\Services\Tournament\LoginCodeService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * Publiczna strona po skanie QR tabletu — deep link do apki + kod tekstowy.
 */
class TournamentTabletLoginLandingController
{
    public function __construct(
        private LoginCodeService $loginCodeService,
    ) {
    }

    public function show(string $code): Factory|View
    {
        $normalized = strtoupper(trim($code));
        $loginCode = $this->loginCodeService->findByCode($normalized);
        $appDeepLink = 'twentysix://tablet-login/'.$normalized;

        return view('tournaments.tablet-login-landing', [
            'code' => $normalized,
            'tournament' => $loginCode?->tournament,
            'appDeepLink' => $appDeepLink,
        ]);
    }
}
