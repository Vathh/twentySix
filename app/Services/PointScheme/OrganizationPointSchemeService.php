<?php

namespace App\Services\PointScheme;

use App\Domain\Tournament\OrganizationPointSchemeValidator;
use App\Domain\Tournament\PointSchemeDomain;
use App\Domain\Tournament\PointSchemeRuleDomain;
use App\Domain\Tournament\SeasonPointTable;
use App\Enums\PointSchemeFormat;
use App\Repositories\PointScheme\PointSchemeRepository;
use App\Repositories\Tournament\TournamentRepository;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationPointSchemeService
{
    public function __construct(
        private PointSchemeRepository $pointSchemeRepository,
        private TournamentRepository $tournamentRepository,
    ) {}

    /**
     * Pięć pasm do formularza. Brak zapisu organizacji = tabele systemowe.
     *
     * @return list<array{
     *     name: string,
     *     min_players: int,
     *     max_players: int,
     *     se: list<array{place_from: int, place_to: int, points: int}>,
     *     de: list<array{place_from: int, place_to: int, points: int}>
     * }>
     */
    public function formState(int $organizationId): array
    {
        $saved = $this->pointSchemeRepository
            ->findActiveForOrganization($organizationId, withRules: true)
            ->keyBy(fn (PointSchemeDomain $scheme) => $scheme->minPlayers);

        $state = [];
        foreach (SeasonPointTable::definitions() as $band) {
            $scheme = $saved->get($band['min_players']);
            $state[] = [
                'name' => $band['name'],
                'min_players' => $band['min_players'],
                'max_players' => $band['max_players'],
                'se' => $scheme instanceof PointSchemeDomain
                    ? $this->rowsFor($scheme, PointSchemeFormat::Se)
                    : $this->rowsFromDefinition($band['rules'], PointSchemeFormat::Se),
                'de' => $scheme instanceof PointSchemeDomain
                    ? $this->rowsFor($scheme, PointSchemeFormat::De)
                    : $this->rowsFromDefinition($band['rules'], PointSchemeFormat::De),
            ];
        }

        return $state;
    }

    public function matchesSystemDefaults(int $organizationId): bool
    {
        return $this->signature($this->formState($organizationId)) === $this->signature($this->defaultBands());
    }

    /**
     * @throws ValidationException
     */
    public function save(int $organizationId, mixed $bands): void
    {
        if (! is_array($bands)) {
            throw ValidationException::withMessages([
                'bands' => 'Brak tabel punktacji.',
            ]);
        }

        $this->persist($organizationId, OrganizationPointSchemeValidator::normalize($bands));
    }

    public function resetToDefaults(int $organizationId): void
    {
        $this->persist($organizationId, SeasonPointTable::definitions());
    }

    /**
     * @param  list<array{
     *     name: string,
     *     min_players: int,
     *     max_players: int,
     *     rules: list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>
     * }>  $bands
     */
    private function persist(int $organizationId, array $bands): void
    {
        DB::transaction(function () use ($organizationId, $bands) {
            $active = $this->pointSchemeRepository->findActiveForOrganization($organizationId);
            $ids = $active->map(fn (PointSchemeDomain $scheme) => $scheme->id)->all();
            $referenced = $this->tournamentRepository->anyUsePointSchemes($ids);

            if ($this->hasCompleteActiveSet($active) && ! $referenced) {
                foreach ($bands as $band) {
                    $scheme = $active->first(
                        fn (PointSchemeDomain $item) => $item->minPlayers === $band['min_players'],
                    );
                    if (! $scheme instanceof PointSchemeDomain) {
                        throw new DomainException('Brak aktywnego pasma punktacji organizacji.');
                    }
                    $this->pointSchemeRepository->replaceRules($scheme->id, $band['rules']);
                }

                return;
            }

            if ($ids !== []) {
                if ($referenced) {
                    $this->pointSchemeRepository->deactivate($ids);
                } else {
                    $this->pointSchemeRepository->deleteWithRules($ids);
                }
            }

            foreach ($bands as $band) {
                $this->pointSchemeRepository->createForOrganization(
                    $organizationId,
                    $band['name'],
                    $band['min_players'],
                    $band['max_players'],
                    $band['rules'],
                );
            }
        });
    }

    /**
     * @param  Collection<int, PointSchemeDomain>  $schemes
     */
    private function hasCompleteActiveSet(Collection $schemes): bool
    {
        $mins = $schemes->map(fn (PointSchemeDomain $scheme) => $scheme->minPlayers)->sort()->values()->all();
        $expected = array_map(
            fn (array $band) => $band['min_players'],
            SeasonPointTable::definitions(),
        );
        sort($expected);

        return $mins === $expected;
    }

    /**
     * @param  list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>  $rules
     * @return list<array{place_from: int, place_to: int, points: int}>
     */
    private function rowsFromDefinition(array $rules, PointSchemeFormat $format): array
    {
        $rows = [];
        foreach ($rules as $rule) {
            if ($rule['format'] !== $format) {
                continue;
            }

            $rows[] = [
                'place_from' => $rule['place_from'],
                'place_to' => $rule['place_to'],
                'points' => $rule['points'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{place_from: int, place_to: int, points: int}>
     */
    private function rowsFor(PointSchemeDomain $scheme, PointSchemeFormat $format): array
    {
        return $scheme->rules
            ->filter(fn (PointSchemeRuleDomain $rule) => $rule->format === $format)
            ->sortBy(fn (PointSchemeRuleDomain $rule) => $rule->placeFrom)
            ->map(fn (PointSchemeRuleDomain $rule) => [
                'place_from' => $rule->placeFrom,
                'place_to' => $rule->placeTo,
                'points' => $rule->points,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     name: string,
     *     min_players: int,
     *     max_players: int,
     *     se: list<array{place_from: int, place_to: int, points: int}>,
     *     de: list<array{place_from: int, place_to: int, points: int}>
     * }>
     */
    private function defaultBands(): array
    {
        $state = [];
        foreach (SeasonPointTable::definitions() as $band) {
            $state[] = [
                'name' => $band['name'],
                'min_players' => $band['min_players'],
                'max_players' => $band['max_players'],
                'se' => $this->rowsFromDefinition($band['rules'], PointSchemeFormat::Se),
                'de' => $this->rowsFromDefinition($band['rules'], PointSchemeFormat::De),
            ];
        }

        return $state;
    }

    /**
     * @param  list<array{min_players: int, se: list<array<string, int>>, de: list<array<string, int>>}>  $bands
     */
    private function signature(array $bands): string
    {
        $slim = array_map(fn (array $band) => [
            'min_players' => $band['min_players'],
            'se' => $band['se'],
            'de' => $band['de'],
        ], $bands);

        return json_encode($slim) ?: '';
    }
}
