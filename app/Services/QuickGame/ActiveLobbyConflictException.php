<?php

namespace App\Services\QuickGame;

/**
 * Konflikt: użytkownik ma już aktywne lobby (waiting/started).
 */
class ActiveLobbyConflictException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $existingLobbyId = null,
        public readonly ?string $existingStatus = null,
    ) {
        parent::__construct($message);
    }
}
