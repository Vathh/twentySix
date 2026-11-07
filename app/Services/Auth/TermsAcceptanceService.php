<?php

namespace App\Services\Auth;

use App\Models\Users\User;
use App\Repositories\User\UserRepository;

class TermsAcceptanceService
{
    public function __construct(private UserRepository $users) {}

    public function accept(User $user): void
    {
        $this->users->markTermsAccepted($user);
    }
}
