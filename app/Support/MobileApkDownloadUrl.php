<?php

namespace App\Support;

final class MobileApkDownloadUrl
{
    /**
     * APK hostowane pod /downloads/ serwujemy z bieżącej domeny strony.
     * Zewnętrzny link (np. strona buildu EAS) zostaje bez zmian.
     */
    public static function href(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/downloads/')) {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
