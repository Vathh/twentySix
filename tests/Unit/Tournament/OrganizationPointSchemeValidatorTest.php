<?php

namespace Tests\Unit\Tournament;

use App\Domain\Tournament\OrganizationPointSchemeValidator;
use App\Domain\Tournament\SeasonPointTable;
use App\Enums\PointSchemeFormat;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationPointSchemeValidatorTest extends TestCase
{
    public function test_default_table_is_valid(): void
    {
        $normalized = OrganizationPointSchemeValidator::normalize($this->payload());

        $this->assertCount(5, $normalized);
        $this->assertSame(13, $normalized[0]['rules'][0]['points']);
        $this->assertSame(PointSchemeFormat::Se, $normalized[0]['rules'][0]['format']);
    }

    public function test_gap_is_rejected(): void
    {
        $bands = $this->payload();
        $bands[4]['se'] = [
            ['place_from' => 1, 'place_to' => 2, 'points' => 10],
            ['place_from' => 4, 'place_to' => 8, 'points' => 4],
        ];

        $this->expectException(ValidationException::class);
        OrganizationPointSchemeValidator::normalize($bands);
    }

    public function test_overlap_is_rejected(): void
    {
        $bands = $this->payload();
        $bands[4]['se'] = [
            ['place_from' => 1, 'place_to' => 4, 'points' => 10],
            ['place_from' => 4, 'place_to' => 8, 'points' => 4],
        ];

        try {
            OrganizationPointSchemeValidator::normalize($bands);
            $this->fail('Nakładka powinna zostać odrzucona.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('nakładek', $exception->validator->errors()->first());
        }
    }

    public function test_worse_place_cannot_score_more(): void
    {
        $bands = $this->payload();
        $bands[4]['de'][4]['points'] = 100;

        try {
            OrganizationPointSchemeValidator::normalize($bands);
            $this->fail('Rosnące punkty powinny zostać odrzucone.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('więcej punktów', $exception->validator->errors()->first());
        }
    }

    public function test_place_outside_band_is_rejected(): void
    {
        $bands = $this->payload();
        $bands[4]['se'][4]['place_to'] = 9;

        try {
            OrganizationPointSchemeValidator::normalize($bands);
            $this->fail('Miejsce poza pasmem powinno zostać odrzucone.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('1–8', $exception->validator->errors()->first());
        }
    }

    /**
     * @return array<int, array{se: list<array{place_from: int, place_to: int, points: int}>, de: list<array{place_from: int, place_to: int, points: int}>}>
     */
    private function payload(): array
    {
        $bands = [];
        foreach (SeasonPointTable::definitions() as $band) {
            $min = $band['min_players'];
            $bands[$min] = ['se' => [], 'de' => []];
            foreach ($band['rules'] as $rule) {
                $bands[$min][$rule['format']->value][] = [
                    'place_from' => $rule['place_from'],
                    'place_to' => $rule['place_to'],
                    'points' => $rule['points'],
                ];
            }
        }

        return $bands;
    }
}
