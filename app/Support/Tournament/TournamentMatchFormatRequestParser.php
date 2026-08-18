<?php

namespace App\Support\Tournament;

use App\Enums\GameStage;
use App\Domain\GameScoring\MatchFormat;
use Illuminate\Validation\ValidationException;

final class TournamentMatchFormatRequestParser
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<string, int|string>>
     */
    public static function fromRunInput(array $input, int $playoffBracketSize, bool $includeGroupStage = true): array
    {
        $requiredStages = $includeGroupStage
            ? GameStage::forPlayoffBracketSize($playoffBracketSize)
            : GameStage::forEliminationBracketSize($playoffBracketSize);
        $raw = $input['matchFormats'] ?? [];

        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'matchFormats' => 'Nieprawidłowy format gry turnieju.',
            ]);
        }

        $formatsByStage = [];

        foreach ($requiredStages as $stage) {
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
     * @param  array<string, array<string, int|string>>|null  $organizationPresets
     * @return array<string, array<string, int|string>>
     */
    public static function defaultsForBracketSize(int $playoffBracketSize, ?array $organizationPresets = null): array
    {
        $byStage = \App\Support\Organization\OrganizationMatchFormatPresets::defaultsByStage($organizationPresets);
        $formatsByStage = [];

        foreach (GameStage::forPlayoffBracketSize($playoffBracketSize) as $stage) {
            $formatsByStage[$stage->value] = $byStage[$stage->value]
                ?? MatchFormat::default()->toArray();
        }

        return $formatsByStage;
    }

    /**
     * @param  array<string, array<string, int|string>>|null  $organizationPresets
     * @return array<string, array<string, int|string>>
     */
    public static function defaultsForEliminationBracketSize(int $bracketSize, ?array $organizationPresets = null): array
    {
        $byStage = \App\Support\Organization\OrganizationMatchFormatPresets::defaultsByStage($organizationPresets);
        $formatsByStage = [];

        foreach (GameStage::forEliminationBracketSize($bracketSize) as $stage) {
            $formatsByStage[$stage->value] = $byStage[$stage->value]
                ?? MatchFormat::default()->toArray();
        }

        return $formatsByStage;
    }
}
