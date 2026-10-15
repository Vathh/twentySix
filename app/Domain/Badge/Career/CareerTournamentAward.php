<?php

namespace App\Domain\Badge\Career;

/**
 * Odznaki za jeden zakończony turniej, w którym zawodnik rozegrał mecz.
 * Miejsce 4 nie jest podium. Ex aequo wchodzi takim miejscem, jakie jest zapisane.
 */
final class CareerTournamentAward
{
    /**
     * @return list<string>
     */
    public static function keysForPlace(?int $place): array
    {
        $keys = [CareerCatalog::APPEARANCES];

        if ($place !== null && $place >= 1 && $place <= 3) {
            $keys[] = CareerCatalog::PODIUM;
        }

        if ($place === 1) {
            $keys[] = CareerCatalog::TITLES;
        }

        return $keys;
    }
}
