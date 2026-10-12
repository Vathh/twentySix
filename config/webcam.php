<?php

$raw = env('WEBCAM_ICE_SERVERS');
$decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

return [
    /*
     * Serwery ICE dla meczu kamerkowego. Domyślnie sam STUN.
     * TURN (przekaźnik, gdy sieci nie puszczają ruchu bezpośrednio) dopisz w WEBCAM_ICE_SERVERS.
     */
    'ice_servers' => is_array($decoded) && $decoded !== []
        ? $decoded
        : [
            ['urls' => 'stun:stun.l.google.com:19302'],
        ],
];
