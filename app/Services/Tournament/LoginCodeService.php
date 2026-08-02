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
    )
    {
    }

    public function generateCodes(int $amount, int $tournamentId): void
    {
        $result = collect();

        for ($i = 0; $i < $amount; $i++) {
            $result->push(LoginCode::generate());
        }

        $this->loginCodeRepository->save($result, $tournamentId);
    }

    /**
     * @return Collection<int, string>
     */
    public function getCodesForTournament(int $tournamentId): Collection
    {
        return $this->loginCodeRepository->findCodesByTournamentId($tournamentId);
    }

    public function revokeForTournament(int $tournamentId): void
    {
        $this->loginCodeRepository->revokeForTournament($tournamentId);
    }

    /**
     * @return array{token: string, tournamentId: int}
     *
     * @throws AccountAuthException
     */
    public function authenticateForTournament(string $code): array
    {
        $loginCode = $this->loginCodeRepository->findByCodeWithTournament($code);

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












