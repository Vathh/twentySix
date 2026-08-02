<?php

namespace App\Services\Auth;

class AccountAuthException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 401,
    ) {
        parent::__construct($message);
    }
}
