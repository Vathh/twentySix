<?php

namespace App\Services\Tournament;

use App\Enums\TournamentStatus;
use App\Models\Tournament\LoginCode;
use App\Repositories\Tournament\LoginCodeRepository;
use App\Services\Auth\AccountAuthException;
use Illuminate\Support\Collection;

class LoginCodeService
{
    public function __construct(
        private LoginCodeRepository $loginCodeRepository
    ) {
    }

    /**
     * Jeden wspólny kod sędziowski na turniej (wszystkie tablety).
     */
    public function generateForTournament(int $tournamentId): string
    {
        $this->loginCodeRepository->revokeForTournament($tournamentId);
        $code = LoginCode::generate();
        $this->loginCodeRepository->save(collect([$code]), $tournamentId);

        return $code;
    }

    /**
     * @return Collection<int, string>
     */
    public function getCodesForTournament(int $tournamentId): Collection
    {
        return $this->loginCodeRepository->findCodesByTournamentId($tournamentId);
    }

    public function getCodeForTournament(int $tournamentId): ?string
    {
        return $this->getCodesForTournament($tournamentId)->first();
    }

    public function regenerateForTournament(int $tournamentId): string
    {
        return $this->generateForTournament($tournamentId);
    }

    public function revokeForTournament(int $tournamentId): void
    {
        $this->loginCodeRepository->revokeForTournament($tournamentId);
    }

    public function loginUrl(string $code): string
    {
        return rtrim((string) config('app.url'), '/').'/tablet-login/'.strtoupper($code);
    }

    public function findByCode(string $code): ?LoginCode
    {
        return $this->loginCodeRepository->findByCodeWithTournament(strtoupper(trim($code)));
    }

    /**
     * @return array{token: string, tournamentId: int}
     *
     * @throws AccountAuthException
     */
    public function authenticateForTournament(string $code): array
    {
        $loginCode = $this->findByCode($code);

        if ($loginCode === null || $loginCode->tournament === null) {
            throw new AccountAuthException('Nieprawidłowy kod logowania', 401);
        }

        if ($loginCode->tournament->status === TournamentStatus::FINISHED) {
            throw new AccountAuthException(
                'Turniej zakończony — kody sędziowania są już nieważne.',
                401,
            );
        }

        $token = $loginCode->createToken('counter')->plainTextToken;

        return [
            'token' => $token,
            'tournamentId' => $loginCode->tournament_id,
        ];
    }
}
