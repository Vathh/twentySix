<?php
namespace App\Domain;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Models\Organization\Organization;
use Carbon\Carbon;

class OrganizationDomain
{
    use AssertsRelationsLoaded;

    /** @var list<string> */
    private const RELATIONS = ['seasons', 'admins', 'relatedUsers', 'guests'];

    /**
     * @param  array<string, array<string, int|string>>  $matchFormatPresets
     * @param  array  $admins
     * @param  array<SeasonDomain>  $seasons
     * @param  array  $relatedUsers
     * @param  array  $guests
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly Carbon $createdAt,
        public readonly Carbon $updatedAt,
        public readonly array $admins,
        public readonly array $seasons,
        public readonly array $relatedUsers,
        public readonly array $guests,
        public readonly array $matchFormatPresets = [],
    )
    {}

    /**
     * @param Organization $organization
     * @param array $with
     * @return self
     */
    public static function fromEloquent(Organization $organization, array $with = []): self
    {
        self::assertRelationsLoaded($organization, $with, self::RELATIONS);

        $presets = is_array($organization->match_format_presets) ? $organization->match_format_presets : [];

        return new self(
            id: $organization->id,
            name: $organization->name,
            description: $organization->description,
            createdAt: $organization->created_at,
            updatedAt: $organization->updated_at,
            admins: in_array('admins', $with)
                ? $organization->admins->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name,
                ])->toArray()
                : [],
            seasons: in_array('seasons', $with)
                ? $organization->seasons->map(fn($season) => SeasonDomain::fromEloquent($season))->toArray()
                : [],
            relatedUsers: in_array('relatedUsers', $with)
                ? $organization->relatedUsers->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name,
                ])->toArray()
                : [],
            guests: in_array('guests', $with)
                ? $organization->guests->map(fn($guest) => [
                    'id' => $guest->id,
                    'name' => $guest->name
                ])->toArray()
                : [],
            matchFormatPresets: $presets,
        );
    }

    public function updatedAtDate(): string
    {
        return $this->updatedAt->format('Y-m-d');
    }

    public function createdAtDate(): string
    {
        return $this->createdAt->format('Y-m-d');
    }

    /** Tytuł kafelka na liście (spójnie z sezonami / turniejami). */
    public function displayTitle(): string
    {
        return $this->name;
    }

    /**
     * Drugi wiersz kafelka: ostatnia aktywność (data aktualizacji rekordu).
     */
    public function getCardSubtitle(): string
    {
        return 'Ostatnia aktywność: '.$this->getUpdatedAtFormatted();
    }

    public function getUpdatedAtFormatted(): string
    {
        return $this->updatedAt->locale((string) config('app.locale'))->translatedFormat('j F Y');
    }

    public function getAdminsIds(): array
    {
        return array_column($this->admins, 'id');
    }

    public function hasMatchFormatPresets(): bool
    {
        return $this->matchFormatPresets !== [];
    }
}
