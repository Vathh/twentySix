<?php

namespace App\Support\Retention;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ConfirmedEntityDeletion
{
    public static function validate(Request $request, string $name): void
    {
        $request->validate([
            'delete_password' => ['required', 'current_password'],
            'entity_name_confirmation' => ['required', 'string', Rule::in([$name])],
        ], [
            'delete_password.current_password' => 'Hasło jest nieprawidłowe.',
            'entity_name_confirmation.in' => 'Wpisz dokładnie nazwę, żeby potwierdzić usunięcie.',
        ]);
    }
}
