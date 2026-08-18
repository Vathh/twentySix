<?php

namespace App\Domain\League;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Okna kolejek: start sezonu + stała długość (np. 7 albo 14 dni).
 */
final class LeagueMatchdayCalendar
{
    /**
     * @return list<array{round_number: int, window_start: CarbonInterface, window_end: CarbonInterface}>
     */
    public static function windows(CarbonInterface $seasonStart, int $lengthDays, int $roundCount): array
    {
        if ($lengthDays < 1) {
            throw new InvalidArgumentException('Kolejka musi trwać przynajmniej 1 dzień.');
        }
        if ($roundCount < 1) {
            return [];
        }

        $start = Carbon::parse($seasonStart)->startOfDay();
        $windows = [];
        for ($i = 0; $i < $roundCount; $i++) {
            $from = $start->copy()->addDays($i * $lengthDays);
            $to = $from->copy()->addDays($lengthDays)->subSecond();
            $windows[] = [
                'round_number' => $i + 1,
                'window_start' => $from,
                'window_end' => $to,
            ];
        }

        return $windows;
    }

    /**
     * Równy podział całego sezonu na kolejki (start i koniec zadane przez admina).
     *
     * @return list<array{round_number: int, window_start: CarbonInterface, window_end: CarbonInterface}>
     */
    public static function equalSpanWindows(
        CarbonInterface $seasonStart,
        CarbonInterface $seasonEnd,
        int $roundCount,
    ): array {
        if ($roundCount < 1) {
            return [];
        }

        $start = Carbon::parse($seasonStart)->startOfDay();
        $end = Carbon::parse($seasonEnd)->endOfDay();
        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('Data zakończenia nie może być wcześniejsza niż start.');
        }

        $span = max(1, $start->diffInSeconds($end));
        $slot = (int) floor($span / $roundCount);
        $windows = [];
        for ($i = 0; $i < $roundCount; $i++) {
            $from = $start->copy()->addSeconds($i * $slot);
            $to = $i === $roundCount - 1
                ? $end
                : $start->copy()->addSeconds(($i + 1) * $slot)->subSecond();
            $windows[] = [
                'round_number' => $i + 1,
                'window_start' => $from,
                'window_end' => $to,
            ];
        }

        return $windows;
    }

    public static function lengthLabel(int $days): string
    {
        return match ($days) {
            1 => '1 dzień',
            7 => 'tydzień (7 dni)',
            14 => 'dwa tygodnie (14 dni)',
            21 => 'trzy tygodnie (21 dni)',
            28 => 'cztery tygodnie (28 dni)',
            default => $days.' dni',
        };
    }
}
