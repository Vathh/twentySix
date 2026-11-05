<?php

namespace App\Support\Organization;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameStage;
use Illuminate\Validation\ValidationException;

final class OrganizationMatchFormatPresets
{
    /**
     * Waliduje input formularza organizacji i zwraca mapę stage => format (toArray).
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
     * Presety drabinki pocieszenia. Brak etapu w formularzu jest błędem.
     * GROUP nie należy do tej drabinki.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, array<string, int|string>>
     */
    public static function consolationFromFormInput(array $raw): array
    {
        if (array_key_exists(GameStage::GROUP->value, $raw)) {
            throw ValidationException::withMessages([
                'consolationMatchFormats.GROUP' => 'Drabinka pocieszenia nie ma fazy grupowej.',
            ]);
        }

        $formatsByStage = [];

        foreach (self::eliminationStages() as $stage) {
            $stageInput = $raw[$stage->value] ?? [];
            if (! is_array($stageInput)) {
                throw ValidationException::withMessages([
                    "consolationMatchFormats.{$stage->value}" => 'Nieprawidłowy format dla etapu '.$stage->label().'.',
                ]);
            }

            try {
                $format = MatchFormat::fromArray($stageInput);
                $format->validateForStage($stage);
            } catch (\DomainException $e) {
                throw ValidationException::withMessages([
                    "consolationMatchFormats.{$stage->value}" => $e->getMessage(),
                ]);
            }

            $formatsByStage[$stage->value] = $format->toArray();
        }

        return $formatsByStage;
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @param  array<string, array<string, int|string>>  $mainStages
     * @return array<string, array<string, int|string>>
     */
    public static function mergeConsolation(?array $stored, array $mainStages, ?array $consolationStages): array
    {
        if ($consolationStages !== null) {
            $mainStages['consolation'] = $consolationStages;

            return $mainStages;
        }

        $existing = is_array($stored['consolation'] ?? null) ? $stored['consolation'] : null;
        if ($existing !== null) {
            $mainStages['consolation'] = $existing;
        }

        return $mainStages;
    }

    /**
     * Domyślne formaty per etap: MatchFormat::default() nadpisane presetami organizacji.
     *
     * @param  array<string, mixed>|null  $organizationPresets
     * @return array<string, array<string, int|string>>
     */
    public static function defaultsByStage(?array $organizationPresets): array
    {
        $fallback = MatchFormat::default()->toArray();
        $out = [];

        foreach (GameStage::cases() as $stage) {
            $stagePreset = is_array($organizationPresets[$stage->value] ?? null)
                ? $organizationPresets[$stage->value]
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
     * Presety pocieszenia, a gdy ich nie ma — preset tego samego szczebla drabinki głównej.
     *
     * @param  array<string, mixed>|null  $organizationPresets
     * @return array<string, array<string, int|string>>
     */
    public static function defaultsForConsolation(?array $organizationPresets): array
    {
        $main = self::defaultsByStage($organizationPresets);
        $block = is_array($organizationPresets['consolation'] ?? null)
            ? $organizationPresets['consolation']
            : [];
        $out = [];

        foreach (self::eliminationStages() as $stage) {
            $stagePreset = is_array($block[$stage->value] ?? null) ? $block[$stage->value] : null;
            if ($stagePreset === null) {
                $out[$stage->value] = $main[$stage->value];

                continue;
            }

            try {
                $format = MatchFormat::fromArray($stagePreset);
                $format->validateForStage($stage);
                $out[$stage->value] = $format->toArray();
            } catch (\Throwable) {
                $out[$stage->value] = $main[$stage->value];
            }
        }

        return $out;
    }

    /**
     * Wartości do formularza edycji organizacji (presety lub domyślne).
     *
     * @param  array<string, mixed>|null  $organizationPresets
     * @return array<string, array<string, int|string>>
     */
    public static function forEditForm(?array $organizationPresets): array
    {
        return self::defaultsByStage($organizationPresets);
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

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function consolationStageOptions(): array
    {
        return array_map(
            static fn (GameStage $stage): array => [
                'value' => $stage->value,
                'label' => $stage->label(),
            ],
            self::eliminationStages(),
        );
    }

    /**
     * @return list<GameStage>
     */
    public static function eliminationStages(): array
    {
        return array_values(array_filter(
            GameStage::cases(),
            static fn (GameStage $stage): bool => $stage !== GameStage::GROUP,
        ));
    }
}
