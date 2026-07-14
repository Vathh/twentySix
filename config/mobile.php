<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile app API token (Sanctum)
    |--------------------------------------------------------------------------
    |
    | Token name: mobile-app (POST /api/account/login).
    | Sliding session: POST /api/account/session/refresh przedłuża ważność.
    |
    */

    'token_ttl_days' => (int) env('MOBILE_TOKEN_TTL_DAYS', 30),

    'token_name' => 'mobile-app',

];
