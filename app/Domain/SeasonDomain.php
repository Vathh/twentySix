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
     * @param  Collection<TournamentDomain>  $tournaments
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
        public readonly array $guests,
        public readonly ?int $tournamentCount = null,
        public readonly ?int $relatedUserCount = null,
        public readonly ?int $guestCount = null,
    ) {}

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
                ? $season->admins->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name,
                ])->toArray()
                : [],
            organization: in_array('organization', $with) && $season->organization
                ? OrganizationDomain::fromEloquent($season->organization)
                : null,
            relatedUsers: in_array('relatedUsers', $with)
                ? $season->relatedUsers->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name,
                ])->toArray()
                : [],
            tournaments: in_array('tournaments', $with)
                ? $season->tournaments->map(fn ($tournament) => TournamentDomain::fromEloquent($tournament))->values()
                : collect(),
            guests: in_array('guests', $with)
                ? $season->guests->map(fn ($guest) => [
                    'id' => $guest->id,
                    'name' => $guest->name,
                ])->toArray()
                : [],
            tournamentCount: $season->tournaments_count !== null
                ? (int) $season->tournaments_count
                : (in_array('tournaments', $with, true) ? $season->tournaments->count() : null),
            relatedUserCount: self::relationCount($season, 'related_users_count', 'relatedUsers', $with),
            guestCount: self::relationCount($season, 'guests_count', 'guests', $with),
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

    /** „1 turniej”, „2 turnieje”, „5 turniejów”. Null, gdy liczby nie załadowano. */
    public function tournamentCountLabel(): ?string
    {
        if ($this->tournamentCount === null) {
            return null;
        }

        $count = $this->tournamentCount;
        $mod10 = $count % 10;
        $mod100 = $count % 100;
        $word = match (true) {
            $count === 1 => 'turniej',
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 'turnieje',
            default => 'turniejów',
        };

        return $count.' '.$word;
    }

    /**
     * @param  list<string>  $with
     */
    private static function relationCount(Season $season, string $countAttribute, string $relation, array $with): ?int
    {
        $counted = $season->getAttribute($countAttribute);
        if ($counted !== null) {
            return (int) $counted;
        }

        if (! in_array($relation, $with, true)) {
            return null;
        }

        return $season->{$relation}->count();
    }
}
