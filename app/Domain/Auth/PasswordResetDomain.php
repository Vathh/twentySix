<?php

namespace App\Domain\Auth;

/**
 * Wspólny komunikat resetu hasła — ten sam, gdy konto jest, i gdy go nie ma.
 */
final class PasswordResetDomain
{
    public const SENT_MESSAGE = 'Jeśli konto z tym adresem istnieje, wysłaliśmy link do ustawienia nowego hasła.';

    public const RESET_MESSAGE = 'Hasło zostało ustawione. Możesz się zalogować.';

    public const INVALID_LINK_MESSAGE = 'Link jest nieprawidłowy albo wygasł. Poproś o nowy.';
}
