<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class CurrentAccessToken
{
    public static function id(?Request $request): ?int
    {
        $token = $request?->user()?->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        return (int) $token->id;
    }
}
