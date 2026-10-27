<?php

namespace App\Domain\Activity;

use App\Domain\Activity\Definitions\PlayerBadgesActivity;
use App\Domain\Activity\Definitions\PlayerFirst180Activity;
use App\Domain\Activity\Definitions\PlayerPersonalBestActivity;
use App\Domain\Activity\Definitions\TournamentChampionActivity;
use InvalidArgumentException;

final class ActivityRegistry
{
    /** @var array<string, ActivityDefinition> */
    private array $byType;

    public function __construct()
    {
        $definitions = [
            new TournamentChampionActivity,
            new PlayerFirst180Activity,
            new PlayerBadgesActivity,
            new PlayerPersonalBestActivity,
        ];

        foreach ($definitions as $definition) {
            $this->byType[$definition->type()->value] = $definition;
        }
    }

    public function get(ActivityType|string $type): ActivityDefinition
    {
        $key = $type instanceof ActivityType ? $type->value : $type;
        if (! isset($this->byType[$key])) {
            throw new InvalidArgumentException("Nieznany typ aktywności: {$key}");
        }

        return $this->byType[$key];
    }

    public function find(string $type): ?ActivityDefinition
    {
        return $this->byType[$type] ?? null;
    }

    /**
     * @return list<string>
     */
    public function typesFor(ActivityChannel $channel): array
    {
        $types = [];
        foreach ($this->byType as $definition) {
            if (in_array($channel, $definition->channels(), true)) {
                $types[] = $definition->type()->value;
            }
        }

        return $types;
    }

    public function sentence(string $type, array $payload): string
    {
        $definition = $this->find($type);
        if ($definition === null) {
            return 'Nowa aktywność.';
        }

        return $definition->sentence($payload);
    }

    /**
     * @return array{code: string, label: string}
     */
    public function kind(string $type): array
    {
        $definition = $this->find($type);

        return $definition?->kind() ?? ['code' => 'story', 'label' => 'Historia'];
    }
}
