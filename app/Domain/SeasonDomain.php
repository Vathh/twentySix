<?php
namespace App\Domain;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Domain\Tournament\TournamentDomain;
use App\Models\Season\Season;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SeasonDomain
{
    use AssertsRelationsLoaded;

    /** @var list<string> */
    private const RELATIONS = ['organization', 'admins', 'relatedUsers', 'tournaments'];

    /**
     * @param int $id
     * @param string $name
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @param Carbon $updatedAt
     * @param array $admins
     * @param OrganizationDomain|null $organization
     * @param array $relatedUsers
     * @param Collection<TournamentDomain> $tournaments
     * @param array $guests
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?Carbon $startDate,
        public readonly ?Carbon $endDate,
        public readonly Carbon $updatedAt,
        public readonly array $admins,
        public readonly ?OrganizationDomain $organization,
        public readonly array $relatedUsers,
        public readonly Collection $tournaments,
        public readonly array $guests
    )
    {
    }

    /**
     * @param Season $season
     * @param array $with
     * @return self
     */
    public static function fromEloquent(Season $season, array $with = []): self
    {
        self::assertRelationsLoaded($season, $with, self::RELATIONS);

        return new self(
            id: $season->id,
            name: $season->name,
            startDate: $season->start_date,
            endDate: $season->end_date,
            updatedAt: $season->updated_at,
            admins: in_array('admins', $with)
                ? $season->admins->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name,
                ])->toArray()
                : [],
            organization: in_array('organization', $with) && $season->organization
                ? OrganizationDomain::fromEloquent($season->organization)
                : null,
            relatedUsers: in_array('relatedUsers', $with)
                ? $season->relatedUsers->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name,
                ])->toArray()
                : [],
            tournaments: in_array('tournaments', $with)
                ? $season->tournaments->map(fn($tournament) => TournamentDomain::fromEloquent($tournament))->values()
                : collect(),
            guests: in_array('guests', $with)
                ? $season->guests->map(fn($guest) => [
                    'id' => $guest->id,
                    'name' => $guest->name
                ])->toArray()
                : []
        );
    }

    public function getStartDate(): ?string
    {
        return $this->startDate?->format('Y-m-d');
    }

    public function getEndDate(): ?string
    {
        return $this->endDate?->format('Y-m-d');
    }

    /** Nagłówek listy: „Organizacja – nazwa sezonu”, gdy znana jest organizacja. */
    public function displayTitle(): string
    {
        $organizationName = $this->organization?->name;
        if (is_string($organizationName) && $organizationName !== '') {
            return $organizationName.' - '.$this->name;
        }

        return $this->name;
    }

    /** Tekst pod kafelkiem: przedział lub pojedyncza data rozgrywek. */
    public function getPlayDatesFormatted(): ?string
    {
        $loc = app()->getLocale();

        if ($this->startDate && $this->endDate) {
            return $this->startDate->locale($loc)->translatedFormat('j F Y')
                .' – '
                .$this->endDate->locale($loc)->translatedFormat('j F Y');
        }
        if ($this->startDate) {
            return 'od '.$this->startDate->locale($loc)->translatedFormat('j F Y');
        }
        if ($this->endDate) {
            return 'do '.$this->endDate->locale($loc)->translatedFormat('j F Y');
        }

        return null;
    }

    public function getUpdatedAtDate(): string
    {
        return $this->updatedAt->format('Y-m-d');
    }
}

