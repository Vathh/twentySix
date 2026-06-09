<?php

namespace Database\Seeders;

use App\DTO\GameAchievementDTO;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\AchievementType;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Models\League\League;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Game\GameService;
use App\Services\Tournament\TournamentService;
use Database\Seeders\Support\DemoGameScoringFactory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * 3 ligi × 2 sezony × 2 turnieje.
 * Pełny cykl (32 graczy, grupy, playoff, finished) + turniej 4-osobowy w fazie grupowej tylko w pierwszej lidze / pierwszym sezonie.
 */
class DemoDataSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'demo-admin@twentysix.local';

    private const BIG_TOURNAMENT = 'Mistrzostwa 32 — pełny bracket (demo)';

    private const SMALL_TOURNAMENT = 'Turniej 4-osobowy (faza grupowa)';

    /** Marker pełnego seeda (3 ligi). */
    private const IDEMPOTENCY_LEAGUE_NAME = 'Białystok — Podlaska Liga Darta';

    private const LEAGUE_NAMES = [
        'twentySix — Liga demonstracyjna',
        'Olsztyn — Warmińsko-Mazurska Liga',
        self::IDEMPOTENCY_LEAGUE_NAME,
    ];

    /**
     * Imiona i ksywy gości demo (duży turniej 32-osobowy). Kolejność = kolejność tworzenia w seederze.
     *
     * @var list<string>
     */
    private const DEMO_GUEST_PLAYER_NAMES = [
        'Marek',
        'Kuba „Trefl”',
        'Tomek',
        'Ola',
        'Bartek',
        'Kasia',
        'Przemek',
        'Ania',
        'Dawid',
        'Martyna',
        'Maciek',
        'Zuza',
        'Łukasz',
        'Grzesiek',
        'Natalia',
        'Wojtek',
        'Klaudia',
        'Michał',
        'Patrycja',
        'Kamil',
        'Iza',
        'Adam',
        'Dominika',
        'Rafał',
        'Sylwia',
        'Paweł',
        'Magda',
        'Jakub',
        'Karolina',
        'Norbert',
        'Filip',
        'Sebastian',
    ];

    public function run(): void
    {
        if (League::where('name', self::IDEMPOTENCY_LEAGUE_NAME)->exists()) {
            return;
        }

        if (League::where('name', self::LEAGUE_NAMES[0])->exists()) {
            $this->command?->warn(
                'Wykryto starą wersję danych demo. Aby załadować 3 ligi × 2 sezony × 2 turnieje, uruchom: php artisan migrate:fresh --seed'
            );

            return;
        }

        $admin = User::query()->where('email', self::ADMIN_EMAIL)->first();
        if ($admin === null) {
            $admin = User::factory()->create([
                'email' => self::ADMIN_EMAIL,
                'password' => 'password',
            ]);
            $admin->forceFill([
                'can_create_leagues' => 1,
                'role' => 'admin',
            ])->save();
        }

        Player::query()->firstOrCreate(
            ['user_id' => $admin->id],
            [
                'name' => 'Administrator demo',
                'league_id' => null,
                'season_id' => null,
            ]
        );

        $tournamentService = app(TournamentService::class);
        $gameService = app(GameService::class);

        $this->seedLeagueSuwalki($admin, $tournamentService, $gameService);
        $this->seedLeagueOlsztyn($admin);
        $this->seedLeagueBialystok($admin);
    }

    private function seedLeagueSuwalki(User $admin, TournamentService $tournamentService, GameService $gameService): void
    {
        $league = League::create([
            'name' => 'twentySix — Liga demonstracyjna',
            'description' => 'Dane wygenerowane przez DemoDataSeeder (m.in. pełny bracket 32).',
        ]);
        $league->admins()->sync([$admin->id]);
        $league->relatedUsers()->sync([$admin->id]);

        $seasonA = $this->createSeason(
            $league,
            $admin,
            'Sezon jesienny 2025',
            now()->subMonths(5)->startOfMonth(),
            now()->addMonth()->endOfMonth(),
        );

        $players = $this->createGuestPlayers(32, $seasonA, $league);

        $big = Tournament::create([
            'season_id' => $seasonA->id,
            'name' => self::BIG_TOURNAMENT,
            'date' => now()->addWeeks(3),
            'status' => TournamentStatus::CREATED,
        ]);
        $ok = $tournamentService->tryCreateGroupGames($big->id, $players->pluck('id')->all(), 8);
        if (! $ok) {
            throw new \RuntimeException('Nie udało się utworzyć faz grupowych turnieju 32-osobowego.');
        }
        $this->finishAllGroupGamesDeterministic($big->id, $gameService);
        $big->refresh();
        if ($big->status !== TournamentStatus::PLAYOFF) {
            throw new \RuntimeException('Oczekiwano statusu playoff po zakończeniu grup — jest: '.$big->status->value);
        }
        $this->finishAllPlayoffGamesPlayer1Wins($big->id, $gameService);
        $big->update(['status' => TournamentStatus::FINISHED]);
        DemoGameScoringFactory::seedTournament($big->id);

        $small = Tournament::create([
            'season_id' => $seasonA->id,
            'name' => self::SMALL_TOURNAMENT,
            'date' => now()->addWeek(),
            'status' => TournamentStatus::CREATED,
        ]);
        $fourIds = $players->take(4)->pluck('id')->all();
        $tournamentService->tryCreateGroupGames($small->id, $fourIds, 2);

        $seasonB = $this->createSeason(
            $league,
            $admin,
            'Sezon wiosenny 2026',
            now()->addMonths(2)->startOfMonth(),
            now()->addMonths(8)->endOfMonth(),
        );
        $this->createPlannedTournament($seasonB, 'Liga klubowa — runda wiosenna', now()->addMonths(3));
        $this->createPlannedTournament($seasonB, 'Puchar miasta — finały', now()->addMonths(5));
    }

    private function seedLeagueOlsztyn(User $admin): void
    {
        $league = League::create([
            'name' => 'Olsztyn — Warmińsko-Mazurska Liga',
            'description' => 'Druga liga demo (turnieje zaplanowane, bez rozgrywek).',
        ]);
        $league->admins()->sync([$admin->id]);
        $league->relatedUsers()->sync([$admin->id]);

        $s1 = $this->createSeason(
            $league,
            $admin,
            'Sezon 2025',
            now()->subYear()->startOfMonth(),
            now()->addMonths(3)->endOfMonth(),
        );
        $this->createPlannedTournament($s1, 'Grand Prix Olsztyna', now()->addWeeks(2));
        $this->createPlannedTournament($s1, 'Turniej towarzyski — styczeń', now()->addWeeks(6));

        $s2 = $this->createSeason(
            $league,
            $admin,
            'Sezon 2026',
            now()->addMonths(4)->startOfMonth(),
            now()->addYear()->endOfMonth(),
        );
        $this->createPlannedTournament($s2, 'Otwarcie sezonu', now()->addMonths(5));
        $this->createPlannedTournament($s2, 'Mistrzostwa województwa (eliminacje)', now()->addMonths(7));
    }

    private function seedLeagueBialystok(User $admin): void
    {
        $league = League::create([
            'name' => self::IDEMPOTENCY_LEAGUE_NAME,
            'description' => 'Trzecia liga demo (turnieje zaplanowane, bez rozgrywek).',
        ]);
        $league->admins()->sync([$admin->id]);
        $league->relatedUsers()->sync([$admin->id]);

        $s1 = $this->createSeason(
            $league,
            $admin,
            'Sezon zimowy',
            now()->subMonths(3)->startOfMonth(),
            now()->addMonths(2)->endOfMonth(),
        );
        $this->createPlannedTournament($s1, 'Liga klubowa — kolejka 1', now()->addDays(10));
        $this->createPlannedTournament($s1, 'Weekend z dartem', now()->addDays(24));

        $s2 = $this->createSeason(
            $league,
            $admin,
            'Sezon letni',
            now()->addMonths(3)->startOfMonth(),
            now()->addMonths(10)->endOfMonth(),
        );
        $this->createPlannedTournament($s2, 'Puchar Podlasia', now()->addMonths(4));
        $this->createPlannedTournament($s2, 'Turniej otwarty — lipiec', now()->addMonths(6));
    }

    private function createSeason(League $league, User $admin, string $name, Carbon $start, Carbon $end): Season
    {
        $season = Season::create([
            'league_id' => $league->id,
            'name' => $name,
            'start_date' => $start,
            'end_date' => $end,
        ]);
        $season->admins()->sync([$admin->id]);
        $season->relatedUsers()->sync([$admin->id]);

        return $season;
    }

    private function createPlannedTournament(Season $season, string $name, Carbon $date): Tournament
    {
        return Tournament::create([
            'season_id' => $season->id,
            'name' => $name,
            'date' => $date,
            'status' => TournamentStatus::CREATED,
        ]);
    }

    /**
     * @return Collection<int, Player>
     */
    private function createGuestPlayers(int $count, Season $season, League $league): Collection
    {
        if ($count > count(self::DEMO_GUEST_PLAYER_NAMES)) {
            throw new \InvalidArgumentException(
                'DEMO_GUEST_PLAYER_NAMES: dodaj wpisy albo zmniejsz liczbę gości (jest '.count(self::DEMO_GUEST_PLAYER_NAMES).", potrzeba {$count})."
            );
        }

        $players = collect();
        for ($i = 1; $i <= $count; $i++) {
            $players->push(Player::create([
                'name' => self::DEMO_GUEST_PLAYER_NAMES[$i - 1],
                'user_id' => null,
                'season_id' => $season->id,
                'league_id' => $league->id,
            ]));
        }

        return $players;
    }

    private function finishAllGroupGamesDeterministic(int $tournamentId, GameService $gameService): void
    {
        foreach (range(1, 8) as $groupNumber) {
            $playerOrder = GroupStanding::query()
                ->where('tournament_id', $tournamentId)
                ->where('group_number', $groupNumber)
                ->orderBy('player_id')
                ->pluck('player_id')
                ->all();

            $games = Game::query()
                ->where('tournament_id', $tournamentId)
                ->where('group_number', $groupNumber)
                ->where('status', GameStatus::SCHEDULED)
                ->orderBy('id')
                ->get();

            foreach ($games as $game) {
                $winnerId = $this->pickBetterPlayer($playerOrder, (int) $game->player1_id, (int) $game->player2_id);
                $p1Win = $winnerId === (int) $game->player1_id;

                $dto = new UpdateGameDTO(
                    new GameResultDTO(
                        gameId: $game->id,
                        type: GameType::GROUP,
                        player1Id: (int) $game->player1_id,
                        player2Id: (int) $game->player2_id,
                        player1Score: $p1Win ? 3 : 1,
                        player2Score: $p1Win ? 1 : 3,
                        winnerId: $winnerId,
                        tournamentId: $tournamentId,
                        groupNumber: $groupNumber,
                    ),
                    achievementsDTOs: $this->demoAchievementsForGame(
                        seed: $game->id,
                        tournamentId: $tournamentId,
                        player1Id: (int) $game->player1_id,
                        player2Id: (int) $game->player2_id,
                        winnerId: $winnerId,
                        isPlayoff: false,
                    ),
                );

                if (! $gameService->update($dto)) {
                    throw new \RuntimeException("Błąd zapisu meczu grupowego #{$game->id}.");
                }
            }
        }
    }

    /**
     * @param  list<int>  $playerOrderBestFirst
     */
    private function pickBetterPlayer(array $playerOrderBestFirst, int $player1Id, int $player2Id): int
    {
        $i1 = array_search($player1Id, $playerOrderBestFirst, true);
        $i2 = array_search($player2Id, $playerOrderBestFirst, true);
        if ($i1 === false) {
            return $player2Id;
        }
        if ($i2 === false) {
            return $player1Id;
        }

        return $i1 <= $i2 ? $player1Id : $player2Id;
    }

    private function finishAllPlayoffGamesPlayer1Wins(int $tournamentId, GameService $gameService): void
    {
        $rounds = [
            GameStage::EIGHT,
            GameStage::QUARTER,
            GameStage::SEMI,
            GameStage::THIRD,
            GameStage::FINAL,
        ];

        foreach ($rounds as $round) {
            $games = PlayoffGame::query()
                ->where('tournament_id', $tournamentId)
                ->where('round', $round)
                ->where('status', GameStatus::SCHEDULED)
                ->orderBy('id')
                ->get();

            foreach ($games as $pg) {
                if (! $pg->player1_id || ! $pg->player2_id) {
                    throw new \RuntimeException(
                        "Brak graczy w meczu playoff #{$pg->id} ({$round->value})."
                    );
                }

                $winnerId = (int) $pg->player1_id;
                $dto = new UpdateGameDTO(
                    new GameResultDTO(
                        gameId: $pg->id,
                        type: GameType::PLAYOFF,
                        player1Id: (int) $pg->player1_id,
                        player2Id: (int) $pg->player2_id,
                        player1Score: 3,
                        player2Score: 1,
                        winnerId: $winnerId,
                        tournamentId: $tournamentId,
                        groupNumber: 0,
                    ),
                    achievementsDTOs: $this->demoAchievementsForGame(
                        seed: $pg->id,
                        tournamentId: $tournamentId,
                        player1Id: (int) $pg->player1_id,
                        player2Id: (int) $pg->player2_id,
                        winnerId: $winnerId,
                        isPlayoff: true,
                    ),
                );

                if (! $gameService->update($dto)) {
                    throw new \RuntimeException("Błąd zapisu meczu playoff #{$pg->id}.");
                }
            }
        }
    }

    /**
     * Deterministyczne osiągnięcia demo (jak w aplikacji sędziowskiej: 180, 170+, HF, QF).
     *
     * @return list<GameAchievementDTO>
     */
    private function demoAchievementsForGame(
        int $seed,
        int $tournamentId,
        int $player1Id,
        int $player2Id,
        int $winnerId,
        bool $isPlayoff,
    ): array {
        $hash = crc32("demo-ach-{$seed}-{$player1Id}-{$player2Id}");
        $loserId = $winnerId === $player1Id ? $player2Id : $player1Id;

        $chancePercent = $isPlayoff ? 72 : 55;
        if (($hash % 100) >= $chancePercent) {
            return [];
        }

        $achievements = [];
        $achievements[] = $this->demoAchievementRoll(
            hash: $hash,
            tournamentId: $tournamentId,
            playerId: (($hash >> 4) % 10) < 7 ? $winnerId : $loserId,
        );

        if ((($hash >> 24) % 100) < 14) {
            $secondPlayer = $achievements[0]->playerId === $winnerId ? $loserId : $winnerId;
            $achievements[] = $this->demoAchievementRoll(
                hash: $hash >> 8,
                tournamentId: $tournamentId,
                playerId: $secondPlayer,
            );
        }

        return $achievements;
    }

    private function demoAchievementRoll(int $hash, int $tournamentId, int $playerId): GameAchievementDTO
    {
        $typeRoll = ($hash >> 8) % 100;

        if ($typeRoll < 7) {
            return new GameAchievementDTO($playerId, $tournamentId, null, AchievementType::MAX);
        }

        if ($typeRoll < 20) {
            $value = 170 + (($hash >> 12) % 9);

            return new GameAchievementDTO($playerId, $tournamentId, $value, AchievementType::ONE_SEVENTY);
        }

        if ($typeRoll < 58) {
            $value = 120 + (($hash >> 16) % 50);

            return new GameAchievementDTO($playerId, $tournamentId, $value, AchievementType::HF);
        }

        $value = 9 + (($hash >> 20) % 11);

        return new GameAchievementDTO($playerId, $tournamentId, $value, AchievementType::QF);
    }
}
