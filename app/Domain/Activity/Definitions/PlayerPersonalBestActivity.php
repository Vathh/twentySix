<?php

namespace App\Domain\Activity\Definitions;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\ActivityDefinition;
use App\Domain\Activity\ActivityType;

final class PlayerPersonalBestActivity implements ActivityDefinition
{
    public function type(): ActivityType
    {
        return ActivityType::PlayerPersonalBest;
    }

    public function channels(): array
    {
        return [ActivityChannel::Friends];
    }

    public function subjectType(): string
    {
        return 'match';
    }

    public function replacesExisting(): bool
    {
        return true;
    }

    public function authoredByOrganization(): bool
    {
        return false;
    }

    public function kind(): array
    {
        return ['code' => 'record', 'label' => 'Rekord'];
    }

    public function sentence(array $payload): string
    {
        $actor = (string) ($payload['actorName'] ?? 'Gracz');
        $records = [];
        foreach ($payload['records'] ?? [] as $record) {
            if (! is_array($record)) {
                continue;
            }
            $kind = (string) ($record['kind'] ?? '');
            if (! in_array($kind, ['average', 'hf', 'qf'], true)) {
                continue;
            }
            $records[] = ['kind' => $kind, 'value' => $record['value'] ?? null];
        }

        if (count($records) === 1) {
            return "{$actor} właśnie poprawił ".self::single($records[0]['kind'], $records[0]['value']).'.';
        }

        if (count($records) > 1) {
            $parts = array_map(
                fn (array $record) => self::short($record['kind'], $record['value']),
                $records,
            );

            return "{$actor} właśnie poprawił życiowe rekordy: ".implode(', ', $parts).'.';
        }

        return "{$actor} właśnie poprawił życiowy rekord.";
    }

    private static function single(string $kind, mixed $value): string
    {
        return match ($kind) {
            'average' => 'życiowy rekord średniej meczowej: '.self::average($value),
            'hf' => 'życiowy rekord najwyższego finishu: '.$value,
            'qf' => 'życiowy rekord najszybszej lotki: '.$value,
        };
    }

    private static function short(string $kind, mixed $value): string
    {
        return match ($kind) {
            'average' => 'średnia meczowa '.self::average($value),
            'hf' => 'najwyższy finish '.$value,
            'qf' => 'najszybsza lotka '.$value,
        };
    }

    private static function average(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '');
    }
}
