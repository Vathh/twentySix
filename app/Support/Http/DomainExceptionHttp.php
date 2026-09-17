<?php

namespace App\Support\Http;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Jedna mapa DomainException → HTTP. 422 domyślnie; 409/403 gdy getCode() to CONFLICT/FORBIDDEN.
 */
final class DomainExceptionHttp
{
    public const FORBIDDEN = 403;

    public const CONFLICT = 409;

    public const UNPROCESSABLE = 422;

    public static function status(DomainException $e): int
    {
        return match ($e->getCode()) {
            self::FORBIDDEN => self::FORBIDDEN,
            self::CONFLICT => self::CONFLICT,
            default => self::UNPROCESSABLE,
        };
    }

    public static function render(DomainException $e, Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
            return response()->json(['message' => $e->getMessage()], self::status($e));
        }

        return back()->withInput()->with('error', $e->getMessage());
    }
}
