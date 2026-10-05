<?php

namespace App\Support\GameScoring;

use App\Support\Http\ProvidesErrorReason;
use DomainException;

class ScoringLeaseException extends DomainException implements ProvidesErrorReason
{
    public function __construct(
        private string $leaseReason,
        string $message,
        int $code,
    ) {
        parent::__construct($message, $code);
    }

    public function reason(): string
    {
        return $this->leaseReason;
    }
}
