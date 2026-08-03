<?php

namespace App\Support\League;

use App\Enums\GameStage;
use App\Domain\GameScoring\MatchFormat;
use Illuminate\Validation\ValidationException;

final class LeagueMatchFormatPresets
{
    /**
     * Waliduje input formularza ligi i zwraca mapę stage => format (toArray).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, array<string, int|string>>
     */
    public static function fromFormInput(array $raw): array
    {
        $formatsByStage = [];

        foreach (GameStage::cases() as $stage) {
            $stageInput = $raw[$stage->value] ?? [];
            if (! is_array($stageInput)) {
                throw ValidationException::withMessages([
                    "matchFormats.{$stage->value}" => 'Nieprawidłowy format dla etapu '.$stage->label().'.',
                ]);
            }

            try {
                $format = MatchFormat::fromArray($stageInput);
                $format->validateForStage($stage);
            } catch (\DomainException $e) {
                throw ValidationException::withMessages([
                    "matchFormats.{$stage->value}" => $e->getMessage(),
                ]);
            }

            $formatsByStage[$stage->value] = $format->toArray();
        }

        return $formatsByStage;
    }

    /**
     * Domyślne formaty per etap: MatchFormat::default() nadpisane presetami ligi.
     *
     * @param  array<string, mixed>|null  $leaguePresets
     * @return array<string, array<string, int|string>>
     */
    public static function defaultsByStage(?array $leaguePresets): array
    {
        $fallback = MatchFormat::default()->toArray();
        $out = [];

        foreach (GameStage::cases() as $stage) {
            $stagePreset = is_array($leaguePresets[$stage->value] ?? null)
                ? $leaguePresets[$stage->value]
                : null;

            if ($stagePreset === null) {
                $out[$stage->value] = $fallback;

                continue;
            }

            try {
                $format = MatchFormat::fromArray($stagePreset);
                $format->validateForStage($stage);
                $out[$stage->value] = $format->toArray();
            } catch (\Throwable) {
                $out[$stage->value] = $fallback;
            }
        }

        return $out;
    }

    /**
     * Wartości do formularza edycji ligi (presety lub domyślne).
     *
     * @param  array<string, mixed>|null  $leaguePresets
     * @return array<string, array<string, int|string>>
     */
    public static function forEditForm(?array $leaguePresets): array
    {
        return self::defaultsByStage($leaguePresets);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function stageOptions(): array
    {
        return array_map(
            static fn (GameStage $stage): array => [
                'value' => $stage->value,
                'label' => $stage->label(),
            ],
            GameStage::cases(),
        );
    }
}
