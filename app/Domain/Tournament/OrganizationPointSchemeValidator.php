<?php

namespace App\Domain\Tournament;

use App\Enums\PointSchemeFormat;
use Illuminate\Validation\ValidationException;

/**
 * Zakresy miejsc i punkty organizacji: pełne pokrycie pasma, bez nakładek,
 * gorsze miejsce nie dostaje więcej punktów niż lepsze.
 */
final class OrganizationPointSchemeValidator
{
    private const MAX_POINTS = 65535;

    /**
     * @param  array<mixed>  $bands
     * @return list<array{
     *     name: string,
     *     min_players: int,
     *     max_players: int,
     *     rules: list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>
     * }>
     */
    public static function normalize(array $bands): array
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];
        $normalized = [];

        foreach (SeasonPointTable::definitions() as $definition) {
            $min = $definition['min_players'];
            $max = $definition['max_players'];
            $name = $definition['name'];
            $key = 'bands.'.$min;
            $submitted = $bands[$min] ?? $bands[(string) $min] ?? null;

            if (! is_array($submitted)) {
                $errors[$key][] = 'Brak tabeli dla pasma '.$name.'.';

                continue;
            }

            $rules = [];
            foreach ([PointSchemeFormat::Se, PointSchemeFormat::De] as $format) {
                $formatKey = $key.'.'.$format->value;
                $rows = $submitted[$format->value] ?? null;
                if (! is_array($rows)) {
                    $errors[$formatKey][] = self::label($format, $name).': brak zakresów miejsc.';

                    continue;
                }

                $parsed = self::parseRows($rows, $format, $name, $max, $formatKey, $errors);
                if ($parsed === null) {
                    continue;
                }

                $rules = [
                    ...$rules,
                    ...self::checkedRanges($parsed, $format, $name, $max, $formatKey, $errors),
                ];
            }

            $normalized[] = [
                'name' => $name,
                'min_players' => $min,
                'max_players' => $max,
                'rules' => $rules,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $rows
     * @param  array<string, list<string>>  $errors
     * @return list<array{place_from: int, place_to: int, points: int}>|null
     */
    private static function parseRows(
        array $rows,
        PointSchemeFormat $format,
        string $bandName,
        int $max,
        string $formatKey,
        array &$errors,
    ): ?array {
        $parsed = [];
        $invalid = false;
        $label = self::label($format, $bandName);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $errors[$formatKey][] = $label.': nieprawidłowy wiersz.';
                $invalid = true;

                continue;
            }

            $fromRaw = $row['place_from'] ?? null;
            $toRaw = $row['place_to'] ?? null;
            $pointsRaw = $row['points'] ?? null;

            if (self::blank($fromRaw) && self::blank($toRaw) && self::blank($pointsRaw)) {
                continue;
            }

            $from = self::intOrNull($fromRaw);
            $to = self::intOrNull($toRaw);
            $points = self::intOrNull($pointsRaw);

            if ($from === null || $to === null || $points === null) {
                $errors[$formatKey][] = $label.': uzupełnij miejsce od, miejsce do i punkty liczbami całkowitymi.';
                $invalid = true;

                continue;
            }

            if ($from < 1 || $to > $max || $from > $to) {
                $errors[$formatKey][] = $label.': zakres miejsc musi mieścić się w 1–'.$max.'.';
                $invalid = true;

                continue;
            }

            if ($points < 0 || $points > self::MAX_POINTS) {
                $errors[$formatKey][] = $label.': punkty muszą być liczbą całkowitą od 0 do '.self::MAX_POINTS.'.';
                $invalid = true;

                continue;
            }

            $parsed[] = [
                'place_from' => $from,
                'place_to' => $to,
                'points' => $points,
            ];
        }

        if ($parsed === []) {
            $errors[$formatKey][] = $label.': dodaj zakresy miejsc.';

            return null;
        }

        if ($invalid) {
            return null;
        }

        usort($parsed, fn (array $a, array $b) => $a['place_from'] <=> $b['place_from']
            ?: $a['place_to'] <=> $b['place_to']);

        return $parsed;
    }

    /**
     * @param  list<array{place_from: int, place_to: int, points: int}>  $rows
     * @param  array<string, list<string>>  $errors
     * @return list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>
     */
    private static function checkedRanges(
        array $rows,
        PointSchemeFormat $format,
        string $bandName,
        int $max,
        string $formatKey,
        array &$errors,
    ): array {
        $label = self::label($format, $bandName);
        $cursor = 1;
        $previousPoints = null;
        $rules = [];

        foreach ($rows as $row) {
            if ($row['place_from'] !== $cursor) {
                $errors[$formatKey][] = $label.': miejsca muszą pokrywać 1–'.$max.' bez przerw i nakładek.';

                return [];
            }

            if ($previousPoints !== null && $row['points'] > $previousPoints) {
                $errors[$formatKey][] = $label.': gorsze miejsce nie może mieć więcej punktów niż lepsze.';

                return [];
            }

            $rules[] = [
                'format' => $format,
                'place_from' => $row['place_from'],
                'place_to' => $row['place_to'],
                'points' => $row['points'],
            ];
            $previousPoints = $row['points'];
            $cursor = $row['place_to'] + 1;
        }

        if ($cursor - 1 !== $max) {
            $errors[$formatKey][] = $label.': miejsca muszą pokrywać 1–'.$max.' bez przerw i nakładek.';

            return [];
        }

        return $rules;
    }

    private static function label(PointSchemeFormat $format, string $bandName): string
    {
        $formatLabel = $format === PointSchemeFormat::Se
            ? 'SE / grupy + playoff'
            : 'Podwójna eliminacja';

        return $formatLabel.', '.$bandName;
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private static function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
