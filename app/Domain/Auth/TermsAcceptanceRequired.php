<?php

namespace App\Domain\Auth;

use DomainException;

final class TermsAcceptanceRequired extends DomainException
{
    public const CODE = 'terms_required';

    public function __construct()
    {
        parent::__construct('Zaakceptuj regulamin i politykę prywatności, żeby komentować.');
    }
}
