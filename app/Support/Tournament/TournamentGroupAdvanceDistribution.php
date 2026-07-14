<?php

namespace App\Support\Tournament;

use InvalidArgumentException;

final class TournamentGroupAdvanceDistribution
{
    /**
     * Proporcjonalny podział miejsc awansujących (metoda największych reszt).
     * Większe grupy (niższe numery) dostają nadwyżkę przy remisach reszt.
     *
     * @param list<int> $groupSizes rozmiary grup (grupa 1 = indeks 0)
     * @return list<int> liczba awansujących per grupa (indeks = nr grupy − 1)
     */
    public static function distribute(array $groupSizes, int $bracketSize): array
    {
        $groupsCount = count($groupSizes);

        if ($groupsCount < 1) {
            throw new InvalidArgumentException('Wymagana co najmniej jedna grupa.');
        }

        $playerCount = array_sum($groupSizes);

        if ($playerCount < 1) {
            throw new InvalidArgumentException('Brak zawodników do podziału awansu.');
        }

        if ($bracketSize < $groupsCount) {
            throw new InvalidArgumentException('Drabinka musi mieć co najmniej tyle miejsc, ile jest grup.');
        }

        if ($bracketSize > $playerCount) {
            throw new InvalidArgumentException('Drabinka nie może być większa niż liczba zawodników.');
        }

        $advances = [];
        $remainders = [];

        foreach ($groupSizes as $index => $size) {
            $exact = $bracketSize * $size / $playerCount;
            $advances[$index] = (int) floor($exact);
            $remainders[$index] = $exact - $advances[$index];
        }

        $assigned = array_sum($advances);
        $toAdd = $bracketSize - $assigned;

        if ($toAdd < 0) {
            throw new InvalidArgumentException('Nie udało się rozłożyć miejsc awansujących.');
        }

        $indices = range(0, $groupsCount - 1);

        usort(
            $indices,
            static fn (int $a, int $b): int => $remainders[$b] <=> $remainders[$a] ?: $a <=> $b,
        );

        for ($k = 0; $k < $toAdd; $k++) {
            $advances[$indices[$k]]++;
        }

        foreach ($advances as $index => $count) {
            if ($count < 1) {
                throw new InvalidArgumentException('Każda grupa musi mieć co najmniej jedno miejsce awansujące.');
            }

            if ($count > $groupSizes[$index]) {
                throw new InvalidArgumentException('Awans przekracza rozmiar grupy.');
            }
        }

        if (array_sum($advances) !== $bracketSize) {
            throw new InvalidArgumentException('Suma miejsc awansujących nie zgadza się z rozmiarem drabinki.');
        }

        return array_values($advances);
    }
}
