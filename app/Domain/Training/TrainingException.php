<?php

namespace App\Domain\Training;

use App\Support\Http\ProvidesErrorReason;
use DomainException;

class TrainingException extends DomainException implements ProvidesErrorReason
{
    public function __construct(
        string $message,
        private string $errorReason,
        int $code = 0,
    ) {
        parent::__construct($message, $code);
    }

    public function reason(): string
    {
        return $this->errorReason;
    }
}
