<?php

namespace App\Support\Broadcasting;

/**
 * Konfiguracja klienta Reverb dla przeglądarki / mobile.
 * Tylko config() — bezpieczne po `php artisan config:cache`.
 */
final class ReverbClientConfig
{
    /**
     * @return array{key: string, host: string, port: int, scheme: string}
     */
    public static function forWeb(): array
    {
        return [
            'key' => (string) config('broadcasting.connections.reverb.key'),
            'host' => (string) config('broadcasting.connections.reverb.client.host'),
            'port' => (int) config('broadcasting.connections.reverb.client.port'),
            'scheme' => (string) config('broadcasting.connections.reverb.client.scheme'),
        ];
    }
}
