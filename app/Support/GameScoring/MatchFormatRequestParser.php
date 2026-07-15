<?php

namespace App\Support\GameScoring;

final class MatchFormatRequestParser
{
    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input, ?MatchFormat $fallback = null): ?MatchFormat
    {
        if (isset($input['matchFormat']) && is_array($input['matchFormat'])) {
            $format = MatchFormat::fromArray($input['matchFormat']);
            $format->validate();

            return $format;
        }

        $hasField = static fn (string $key): bool => array_key_exists($key, $input) && $input[$key] !== null;

        if (
            ! $hasField('legsToWinSet')
            && ! $hasField('setsToWinMatch')
            && ! $hasField('startingScore')
            && ! $hasField('gameType')
        ) {
            return null;
        }

        $base = $fallback?->toArray() ?? MatchFormat::default()->toArray();

        if ($hasField('legsToWinSet')) {
            $base['legsToWinSet'] = (int) $input['legsToWinSet'];
        }
        if ($hasField('setsToWinMatch')) {
            $base['setsToWinMatch'] = (int) $input['setsToWinMatch'];
        }
        if ($hasField('startingScore')) {
            $base['startingScore'] = (int) $input['startingScore'];
        }
        if ($hasField('gameType')) {
            $gameType = (string) $input['gameType'];
            $base['gameType'] = $gameType === '501' ? 'x01' : $gameType;
            if ($gameType === '501' && ! $hasField('startingScore')) {
                $base['startingScore'] = 501;
            }
        }

        $format = MatchFormat::fromArray($base);
        $format->validate();

        return $format;
    }
}
