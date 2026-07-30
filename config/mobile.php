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

    /*
    |--------------------------------------------------------------------------
    | Publiczny link do APK (Android)
    |--------------------------------------------------------------------------
    |
    | Strona buildu EAS albo bezpośredni artifact (.apk).
    | Po nowym buildzie zaktualizuj MOBILE_APK_DOWNLOAD_URL w .env.
    |
    */

    'apk_download_url' => env(
        'MOBILE_APK_DOWNLOAD_URL',
        'https://expo.dev/accounts/vathh/projects/twentysix/builds/e77b020d-155e-42a6-9a35-b18639fc6796',
    ),

];
