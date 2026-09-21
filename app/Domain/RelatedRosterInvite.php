<?php

namespace App\Domain;

/**
 * Wynik zaproszenia do składu: albo zaproszenie do akceptacji, albo natychmiastowy wpis (admin dodaje siebie).
 */
final class RelatedRosterInvite
{
    public const ADMIN_MEMBERSHIP_MESSAGE = 'Administrator jest w składzie z urzędu.';

    public const SELF_JOIN_MESSAGE = 'Dodano Cię do składu';

    /**
     * @param  array{id: int, name: string}|null  $member
     */
    private function __construct(
        public readonly ?object $invitation,
        public readonly ?array $member,
    ) {}

    public static function invitation(object $invitation): self
    {
        return new self($invitation, null);
    }

    /**
     * @param  array{id: int, name: string}  $member
     */
    public static function member(array $member): self
    {
        return new self(null, $member);
    }

    public function joinedImmediately(): bool
    {
        return $this->member !== null;
    }
}
