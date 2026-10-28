<?php

namespace App\Domain\Activity;

interface ActivityDefinition
{
    public function type(): ActivityType;

    /**
     * @return list<ActivityChannel>
     */
    public function channels(): array;

    public function subjectType(): string;

    public function replacesExisting(): bool;

    /** Nagłówek karty to organizacja. Zdanie nadal nazywa zawodnika. */
    public function authoredByOrganization(): bool;

    /**
     * Rodzaj osiągnięcia na karcie. Nowy typ wydarzenia deklaruje swój rodzaj tutaj.
     *
     * @return array{code: string, label: string}
     */
    public function kind(): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sentence(array $payload): string;
}
