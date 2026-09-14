<?php

namespace App\Support\Broadcasting;

/**
 * Konfiguracja klienta Reverb dla przeglądarki / mobile.
 * Host z configu (REVERB_HOST), z wyjątkiem podglądu na loopback —
 * wtedy WS idzie na ten sam host co strona (127.0.0.1 / localhost),
 * a nie na IP LAN ustawione pod telefony.
 */
final class ReverbClientConfig
{
    /**
     * @return array{key: string, host: string, port: int, scheme: string}
     */
    public static function forWeb(): array
    {
        $host = (string) config('broadcasting.connections.reverb.client.host');
        $pageHost = request()->getHost();
        if (in_array($pageHost, ['127.0.0.1', 'localhost'], true)) {
            $host = $pageHost;
        }

        return [
            'key' => (string) config('broadcasting.connections.reverb.key'),
            'host' => $host,
            'port' => (int) config('broadcasting.connections.reverb.client.port'),
            'scheme' => (string) config('broadcasting.connections.reverb.client.scheme'),
        ];
    }
}
