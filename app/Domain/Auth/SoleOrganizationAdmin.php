<?php

namespace App\Domain\Auth;

use DomainException;

final class SoleOrganizationAdmin extends DomainException
{
    /**
     * @param  list<string>  $organizationNames
     */
    public function __construct(array $organizationNames)
    {
        $quoted = implode(', ', array_map(
            fn (string $name) => '„'.$name.'”',
            $organizationNames,
        ));

        parent::__construct(
            'Jesteś jedynym administratorem organizacji '.$quoted.'. Wskaż innego administratora, zanim usuniesz konto.'
        );
    }
}
