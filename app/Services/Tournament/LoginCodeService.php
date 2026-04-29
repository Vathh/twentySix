<?php

namespace App\Services\Tournament;

use App\Models\LoginCode;
use App\Repositories\Tournament\LoginCodeRepository;
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
}











