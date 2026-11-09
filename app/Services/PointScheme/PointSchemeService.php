<?php

namespace App\Services\PointScheme;

use App\Domain\Tournament\PointSchemeDomain;
use App\Repositories\PointScheme\PointSchemeRepository;
use DomainException;
use Illuminate\Support\Collection;

class PointSchemeService
{
    public function __construct(
        private PointSchemeRepository $pointSchemeRepository,
    ) {}

    public function findByPlayersAmount(int $playersAmount, ?int $organizationId = null): PointSchemeDomain
    {
        if ($organizationId !== null) {
            $organizationMatch = $this->matchPlayers(
                $this->pointSchemeRepository->findActiveForOrganization($organizationId),
                $playersAmount,
            );
            if ($organizationMatch !== null) {
                return $organizationMatch;
            }
        }

        $systemMatch = $this->matchPlayers(
            $this->pointSchemeRepository->findSystem(),
            $playersAmount,
        );

        if ($systemMatch === null) {
            throw new DomainException(
                'Brak schematu punktowego dla liczby graczy: '.$playersAmount
                .'. Przedziały `min_players` / `max_players` definiuje `database/seeders/PointSchemeSeeder.php`.'
            );
        }

        return $systemMatch;
    }

    /**
     * Przy nakładających się zakresach wybierz „wyższy” przedział — większe min_players.
     *
     * @param  Collection<int, PointSchemeDomain>  $schemes
     */
    private function matchPlayers(Collection $schemes, int $playersAmount): ?PointSchemeDomain
    {
        $match = $schemes
            ->filter(fn (PointSchemeDomain $scheme) => $playersAmount >= $scheme->minPlayers
                && $playersAmount <= $scheme->maxPlayers)
            ->sortByDesc(fn (PointSchemeDomain $scheme) => $scheme->minPlayers)
            ->first();

        return $match instanceof PointSchemeDomain ? $match : null;
    }
}
