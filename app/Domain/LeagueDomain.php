<?php
namespace App\Domain;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Models\League\League;
use Carbon\Carbon;

class LeagueDomain
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
     * @param League $league
     * @param array $with
     * @return self
     */
    public static function fromEloquent(League $league, array $with = []): self
    {
        self::assertRelationsLoaded($league, $with, self::RELATIONS);

        $presets = is_array($league->match_format_presets) ? $league->match_format_presets : [];

        return new self(
            id: $league->id,
            name: $league->name,
            description: $league->description,
            createdAt: $league->created_at,
            updatedAt: $league->updated_at,
            admins: in_array('admins', $with)
                ? $league->admins->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name,
                ])->toArray()
                : [],
            seasons: in_array('seasons', $with)
                ? $league->seasons->map(fn($season) => SeasonDomain::fromEloquent($season))->toArray()
                : [],
            relatedUsers: in_array('relatedUsers', $with)
                ? $league->relatedUsers->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->player->name,
                ])->toArray()
                : [],
            guests: in_array('guests', $with)
                ? $league->guests->map(fn($guest) => [
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
