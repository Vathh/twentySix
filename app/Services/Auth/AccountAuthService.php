<?php

namespace App\Services\Auth;

use App\Repositories\User\UserRepository;
use App\Services\Tournament\LoginCodeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountAuthService
{
    public function __construct(
        private UserRepository $userRepository,
        private MobileAppTokenService $mobileAppTokenService,
        private LoginCodeService $loginCodeService,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function attemptWebLogin(string $email, string $password): void
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user !== null
            && Hash::check($password, $user->password)
            && ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Potwierdź adres email — sprawdź skrzynkę (link z rejestracji).',
            ]);
        }

        if ($user !== null
            && Hash::check($password, $user->password)
            && $user->isBanned()) {
            throw ValidationException::withMessages([
                'email' => 'Konto zostało zablokowane.',
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'credentials' => __('validation.auth.failed'),
            ]);
        }
    }

    /**
     * @return array{token: string, user: array{id: int, email: string, name: string|null, playerId: int|null}}
     *
     * @throws AccountAuthException
     */
    public function loginApi(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new AccountAuthException('Nieprawidłowy email lub hasło', 401);
        }

        if (! $user->hasVerifiedEmail()) {
            throw new AccountAuthException(
                'Potwierdź adres email — sprawdź skrzynkę (link z rejestracji).',
                403,
            );
        }

        if ($user->isBanned()) {
            throw new AccountAuthException('Konto zostało zablokowane.', 403);
        }

        return $this->mobileAppTokenService->issueForUser($user);
    }

    public function resendVerificationEmail(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * @return array{token: string, tournamentId: int}
     *
     * @throws AccountAuthException
     */
    public function tournamentLogin(string $code): array
    {
        return $this->loginCodeService->authenticateForTournament($code);
    }
}
