<?php

namespace Database\Seeders;

use App\Domain\Career\CareerSnapshotMetrics;
use App\Domain\GameScoring\MatchFormat;
use App\DTO\GameAchievementDTO;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\AchievementType;
use App\Enums\CareerSource;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\GroupStanding\GroupStanding;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\QuickGame\QuickGame;
use App\Models\QuickGame\QuickGameResult;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\Career\PlayerGameSnapshotRepository;
use App\Repositories\Friends\FriendshipRepository;
use App\Services\Career\PlayerCareerSnapshotService;
use App\Services\Career\TrainingGameService;
use App\Services\Game\GameService;
use App\Services\League\LeagueSeasonService;
use App\Services\League\LeagueService;
use App\Services\Player\PlayerService;
use App\Services\Player\PlayerStatsService;
use App\Services\Tournament\TournamentService;
use App\Support\GameScoring\GameScoringContext;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\CareerProfileMetricsFactory;
use Database\Seeders\Support\DemoGameScoringFactory;
use DomainException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Zarejestrowany gracz z ~rokiem historii (trening, quick, turnieje, liga).
 *
 * Logowanie: profil@test.pl / password
 * Idempotentny — ponowne uruchomienie nic nie dopisuje.
 */
class PlayerCareerProfileDemoSeeder extends Seeder
{
    private const EMAIL = 'profil@test.pl';

    private const PASSWORD = 'password';

    private const PLAYER_NAME = 'Karol Zawodnik';

    private const ORGANIZATION_NAME = 'Klub profilu — kariera demo';

    private const LEAGUE_NAME = 'Liga klubowa — sezon profilu';

    private const ADMIN_EMAIL = 'demo-admin@twentysix.local';

    /** @var list<string> */
    private const GUEST_NAMES = [
        'Arek „Double”',
        'Piotrek Q',
        'Asia',
        'Mandżo',
        'Tomek Checkout',
        'Ola 180',
        'Bartek Trefl',
    ];

    /** @var list<array{name: string, date: string}> */
    private const TOURNAMENTS = [
        ['name' => 'Jesienny Open klubu', 'date' => '2025-10-12'],
        ['name' => 'Puchar klubu — listopad', 'date' => '2025-11-29'],
        ['name' => 'Noworoczny 501', 'date' => '2026-01-18'],
        ['name' => 'Grand Prix wiosenny', 'date' => '2026-03-08'],
        ['name' => 'Mistrzostwa klubu', 'date' => '2026-05-17'],
        ['name' => 'Letni challenge', 'date' => '2026-07-12'],
        ['name' => 'Puchar sierpnia', 'date' => '2026-08-23'],
    ];

    public function run(): void
    {
        $this->call(DemoPlayersSeeder::class);

        $existing = Organization::query()->where('name', self::ORGANIZATION_NAME)->first();
        if ($existing !== null) {
            $this->printReady(Player::query()->whereHas('user', fn ($q) => $q->where('email', self::EMAIL))->first());
            $this->call(PlayerCheckoutWheelDemoSeeder::class);

            return;
        }

        $admin = $this->ensureAdmin();
        $user = $this->ensureCareerUser();
        $player = $user->player;
        if ($player === null) {
            throw new RuntimeException('Brak rekordu player dla '.self::EMAIL);
        }

        DB::transaction(function () use ($admin, $user, $player) {
            $opponents = $this->demoOpponents();
            $this->seedFriendships($user, $opponents);

            $organization = Organization::create([
                'name' => self::ORGANIZATION_NAME,
                'description' => 'Dane do UI profilu: rok aktywności Karola Zawodnika.',
            ]);
            $organization->admins()->sync([$admin->id]);
            $organization->relatedUsers()->sync([$admin->id, $user->id]);

            $season = Season::create([
                'organization_id' => $organization->id,
                'name' => 'Sezon 2025/26',
                'start_date' => '2025-09-01',
                'end_date' => '2026-09-30',
            ]);
            $season->admins()->sync([$admin->id]);

            $guests = $this->createSeasonGuests($season, $organization);

            $this->seedTrainings($user);
            $this->seedQuickGames($player, $opponents);
            $this->seedTournaments($season, $player, $guests);
            $this->seedLeague($organization, $admin, $user, $player, $guests);

            app(PlayerStatsService::class)->recalculateAndSave((int) $player->id);
            app(\App\Services\Player\PlayerOverviewService::class)->rebuild((int) $player->id);
        });

        $this->printReady($player->fresh());
        $this->call(PlayerCheckoutWheelDemoSeeder::class);
    }

    private function ensureAdmin(): User
    {
        $admin = User::query()->where('email', self::ADMIN_EMAIL)->first();
        if ($admin === null) {
            $admin = User::factory()->create([
                'email' => self::ADMIN_EMAIL,
                'password' => self::PASSWORD,
            ]);
            $admin->forceFill([
                'can_create_organizations' => true,
                'role' => 'admin',
            ])->save();
        }

        if (Player::query()->where('user_id', $admin->id)->doesntExist()) {
            app(PlayerService::class)->create('Administrator demo', $admin->id);
        }

        return $admin;
    }

    private function ensureCareerUser(): User
    {
        $registeredAt = CarbonImmutable::parse('2025-08-20 19:12:00', 'Europe/Warsaw');

        $user = User::query()->where('email', self::EMAIL)->first();
        if ($user === null) {
            $user = User::factory()->create([
                'email' => self::EMAIL,
                'password' => self::PASSWORD,
            ]);
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? $registeredAt,
            'created_at' => $registeredAt,
            'updated_at' => $registeredAt,
        ])->save();

        $player = Player::query()->where('user_id', $user->id)->first();
        if ($player === null) {
            app(PlayerService::class)->create(self::PLAYER_NAME, $user->id);
            $player = Player::query()->where('user_id', $user->id)->firstOrFail();
        }

        $player->forceFill([
            'name' => self::PLAYER_NAME,
            'description' => 'Gram regularnie od sierpnia 2025. Lubię 501, Around the Clock i wieczorne sparingi w klubie.',
            'created_at' => $registeredAt,
            'updated_at' => $registeredAt,
        ])->save();

        return $user->fresh(['player']);
    }

    /**
     * @return list<Player>
     */
    private function demoOpponents(): array
    {
        $players = [];
        for ($i = 1; $i <= 8; $i++) {
            $user = User::query()->where('email', 'gracz'.$i.'@test.pl')->first();
            $player = $user?->player;
            if ($player !== null) {
                $players[] = $player;
            }
        }

        if ($players === []) {
            throw new RuntimeException('Brak kont demo gracz1–8. Uruchom DemoPlayersSeeder.');
        }

        return $players;
    }

    /**
     * @param  list<Player>  $opponents
     */
    private function seedFriendships(User $user, array $opponents): void
    {
        $repo = app(FriendshipRepository::class);
        foreach (array_slice($opponents, 0, 4) as $friend) {
            if ($friend->user_id) {
                $repo->addFriendship((int) $user->id, (int) $friend->user_id);
            }
        }
    }

    /**
     * @return list<Player>
     */
    private function createSeasonGuests(Season $season, Organization $organization): array
    {
        $guests = [];
        foreach (self::GUEST_NAMES as $name) {
            $guests[] = Player::create([
                'name' => $name,
                'user_id' => null,
                'season_id' => $season->id,
                'organization_id' => $organization->id,
            ]);
        }

        return $guests;
    }

    private function seedTrainings(User $user): void
    {
        $service = app(TrainingGameService::class);
        $start = CarbonImmutable::now('Europe/Warsaw')->startOfDay()->subDays(365);
        $vacationFrom = CarbonImmutable::parse('2026-07-05', 'Europe/Warsaw');
        $vacationTo = CarbonImmutable::parse('2026-07-20', 'Europe/Warsaw');
        $index = 0;

        for ($day = 0; $day < 365; $day++) {
            $at = $start->addDays($day);
            if ($at->gte($vacationFrom) && $at->lte($vacationTo)) {
                continue;
            }

            $h = $this->u('train-'.$at->toDateString());
            $weekday = (int) $at->dayOfWeekIso;
            $trainToday = $weekday <= 5 ? ($h % 5) < 2 : ($h % 4) === 0;
            if (! $trainToday) {
                continue;
            }

            $sessions = ($h % 11) === 0 ? 2 : 1;
            for ($s = 0; $s < $sessions; $s++) {
                $index++;
                $gameType = $this->trainingGameType($h + $s);
                $form = $day / 365;
                $completed = $at->setTime(18 + (($h + $s) % 4), 10 + (($h >> 2) % 40));
                $service->ingest($user, [
                    'clientUuid' => sprintf('c4aee001-0000-4000-a000-%012d', $index),
                    'gameType' => $gameType,
                    'completedAt' => $completed->toIso8601String(),
                    'format' => $this->formatFor($gameType),
                    'metrics' => CareerProfileMetricsFactory::forGameType($gameType, $index * 17, $form, ($h % 3) !== 0),
                ]);
            }
        }
    }

    /**
     * @param  list<Player>  $opponents
     */
    private function seedQuickGames(Player $career, array $opponents): void
    {
        $snapshots = app(PlayerGameSnapshotRepository::class);
        $start = CarbonImmutable::now('Europe/Warsaw')->startOfDay()->subDays(360);
        $count = 0;

        for ($week = 0; $week < 52; $week++) {
            $gamesThisWeek = 1 + ($this->u('qw-'.$week) % 2);
            if ($week >= 44 && $week <= 46) {
                continue;
            }
            for ($g = 0; $g < $gamesThisWeek; $g++) {
                $count++;
                $h = $this->u('quick-'.$count);
                $dayOffset = ($week * 7) + ($h % 6);
                $at = $start->addDays(min(359, $dayOffset))->setTime(19 + ($h % 3), 15 + ($h % 40));
                $form = min(1.0, $dayOffset / 365);
                $gameType = $this->quickGameType($h);
                $ffaSize = ($h % 9) === 0 ? 4 : (($h % 6) === 0 ? 3 : 2);
                $table = array_slice($opponents, 0, min($ffaSize - 1, count($opponents)));
                if ($table === []) {
                    continue;
                }
                $won = ($h % 5) !== 0;
                $this->insertQuickGame($career, $table, $gameType, $at, $form, $won, $count, $snapshots);
            }
        }
    }

    /**
     * @param  list<Player>  $opponents
     */
    private function insertQuickGame(
        Player $career,
        array $opponents,
        string $gameType,
        CarbonImmutable $at,
        float $form,
        bool $won,
        int $seed,
        PlayerGameSnapshotRepository $snapshots,
    ): void {
        $firstOpponent = $opponents[0];
        $careerLegs = $won ? 2 : (($this->u($seed.'l') % 3) === 0 ? 0 : 1);
        $oppLegs = $won ? (($this->u($seed.'o') % 3) === 0 ? 1 : 0) : 2;

        $quick = new QuickGame;
        $quick->fill([
            'player1_id' => $career->id,
            'player2_id' => $firstOpponent->id,
            'player1_score' => $careerLegs,
            'player2_score' => $oppLegs,
            'winner_id' => $won ? $career->id : $firstOpponent->id,
            'status' => GameStatus::FINISHED,
            'starting_score' => 501,
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
            'game_type' => $gameType,
        ]);
        $quick->save();
        $quick->timestamps = false;
        $quick->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        $participants = array_merge([$career], $opponents);
        $place = $won ? 1 : 2;
        foreach ($participants as $index => $participant) {
            $isCareer = (int) $participant->id === (int) $career->id;
            $metrics = CareerProfileMetricsFactory::forGameType(
                $gameType,
                $seed * 31 + $index,
                $form,
                $isCareer ? $won : ! $won && $index === 1,
            );
            $playerPlace = $isCareer ? $place : ($won ? $index + 2 : ($index === 1 ? 1 : $index + 2));
            QuickGameResult::create([
                'quick_game_id' => $quick->id,
                'player_id' => $participant->id,
                'score' => $isCareer ? $careerLegs : ($index === 1 ? $oppLegs : max(0, 2 - $index)),
                'place' => min(count($participants), max(1, $playerPlace)),
                'average' => is_numeric($metrics['average'] ?? null) ? $metrics['average'] : null,
                'darts_thrown' => is_numeric($metrics['darts_thrown'] ?? null) ? (int) $metrics['darts_thrown'] : 21,
                'points_earned' => is_numeric($metrics['points'] ?? null) ? (int) $metrics['points'] : 0,
            ]);
            if ($isCareer) {
                $snapshots->upsertForSourceable(
                    (int) $career->id,
                    CareerSource::Quick,
                    CareerSnapshotMetrics::isX01($gameType) ? MatchFormat::DEFAULT_GAME_TYPE : $gameType,
                    $at,
                    $quick,
                    $metrics,
                );
            }
        }
    }

    /**
     * @param  list<Player>  $guests
     */
    private function seedTournaments(Season $season, Player $career, array $guests): void
    {
        $tournamentService = app(TournamentService::class);
        $gameService = app(GameService::class);
        $snapshotService = app(PlayerCareerSnapshotService::class);

        foreach (self::TOURNAMENTS as $index => $row) {
            $date = CarbonImmutable::parse($row['date'], 'Europe/Warsaw')->setTime(18, 30);
            $tournament = Tournament::create([
                'season_id' => $season->id,
                'name' => $row['name'],
                'date' => $date->toDateString(),
                'status' => TournamentStatus::CREATED,
            ]);
            $tournament->admins()->sync($season->admins->pluck('id')->all());

            $playerIds = array_merge([(int) $career->id], array_map(fn (Player $p) => (int) $p->id, $guests));
            $ok = $tournamentService->tryCreateGroupGames($tournament->id, $playerIds, 2, 4);
            if (! $ok) {
                throw new RuntimeException('Nie udało się wystartować turnieju '.$row['name']);
            }

            $this->finishTournamentGames($tournament->id, $gameService, (int) $career->id, $index);
            $tournament->refresh();
            if ($tournament->status !== TournamentStatus::FINISHED) {
                $tournament->update(['status' => TournamentStatus::FINISHED]);
            }

            DemoGameScoringFactory::seedTournament($tournament->id);
            $this->backdateTournament($tournament->id, $date);
            $this->recordH2hSnapshotsForTournament($tournament->id, $snapshotService);
        }
    }

    private function finishTournamentGames(int $tournamentId, GameService $gameService, int $careerPlayerId, int $tournamentIndex): void
    {
        $skill = $this->skillMap($careerPlayerId, $tournamentIndex);

        $groupNumbers = Game::query()
            ->where('tournament_id', $tournamentId)
            ->distinct()
            ->orderBy('group_number')
            ->pluck('group_number')
            ->all();

        foreach ($groupNumbers as $groupNumber) {
            $games = Game::query()
                ->where('tournament_id', $tournamentId)
                ->where('group_number', $groupNumber)
                ->where('status', GameStatus::SCHEDULED)
                ->orderBy('id')
                ->get();

            foreach ($games as $game) {
                $this->finishOneGame($gameService, $game, GameType::GROUP, (int) $groupNumber, $skill);
            }
        }

        $rounds = [GameStage::SEMI, GameStage::THIRD, GameStage::FINAL];
        foreach ($rounds as $round) {
            $games = PlayoffGame::query()
                ->where('tournament_id', $tournamentId)
                ->where('round', $round->value)
                ->where('status', GameStatus::SCHEDULED)
                ->orderBy('id')
                ->get();

            foreach ($games as $game) {
                if (! $game->player1_id || ! $game->player2_id) {
                    continue;
                }
                $this->finishOneGame($gameService, $game, GameType::PLAYOFF, 0, $skill);
            }
        }
    }

    /**
     * @param  array<int, int>  $skill
     */
    private function finishOneGame(
        GameService $gameService,
        Game|PlayoffGame $game,
        GameType $type,
        int $groupNumber,
        array $skill,
    ): void {
        $p1 = (int) $game->player1_id;
        $p2 = (int) $game->player2_id;
        $h = $this->u('fin-'.$game->id.'-'.$type->value);
        $s1 = $skill[$p1] ?? 50;
        $s2 = $skill[$p2] ?? 50;
        $p1Favorite = $s1 >= $s2;
        $upset = ($h % 100) < 22;
        $p1Wins = $upset ? ! $p1Favorite : $p1Favorite;
        $loserLegs = ($h % 4) === 0 ? 1 : 0;
        $winnerId = $p1Wins ? $p1 : $p2;

        $dto = new UpdateGameDTO(
            new GameResultDTO(
                gameId: (int) $game->id,
                type: $type,
                player1Id: $p1,
                player2Id: $p2,
                player1Score: $p1Wins ? 2 : $loserLegs,
                player2Score: $p1Wins ? $loserLegs : 2,
                winnerId: $winnerId,
                tournamentId: (int) $game->tournament_id,
                groupNumber: $groupNumber,
            ),
            achievementsDTOs: $this->achievementsFor($h, (int) $game->tournament_id, $p1, $p2, $winnerId),
        );

        if (! $gameService->update($dto)) {
            throw new RuntimeException("Błąd zapisu meczu {$type->value} #{$game->id}.");
        }
    }

    /**
     * @return array<int, int>
     */
    private function skillMap(int $careerPlayerId, int $tournamentIndex): array
    {
        $skills = [$careerPlayerId => 71 + ($tournamentIndex % 3)];
        $guestBase = [74, 66, 62, 58, 55, 48, 42];
        $guestIds = Player::query()
            ->whereNull('user_id')
            ->whereIn('name', self::GUEST_NAMES)
            ->pluck('id')
            ->all();
        foreach ($guestIds as $i => $id) {
            $skills[(int) $id] = $guestBase[$i % count($guestBase)] + (($tournamentIndex + $i) % 4) - 1;
        }

        return $skills;
    }

    /**
     * @return list<GameAchievementDTO>
     */
    private function achievementsFor(int $hash, int $tournamentId, int $p1, int $p2, int $winnerId): array
    {
        if (($hash % 100) >= 62) {
            return [];
        }

        $playerId = (($hash >> 4) % 10) < 7 ? $winnerId : ($winnerId === $p1 ? $p2 : $p1);
        $roll = ($hash >> 8) % 100;
        if ($roll < 8) {
            return [new GameAchievementDTO($playerId, $tournamentId, null, AchievementType::MAX)];
        }
        if ($roll < 22) {
            return [new GameAchievementDTO($playerId, $tournamentId, 170 + ($hash % 9), AchievementType::ONE_SEVENTY)];
        }
        if ($roll < 60) {
            return [new GameAchievementDTO($playerId, $tournamentId, 100 + ($hash % 70), AchievementType::HF)];
        }

        return [new GameAchievementDTO($playerId, $tournamentId, 12 + ($hash % 8), AchievementType::QF)];
    }

    private function backdateTournament(int $tournamentId, CarbonImmutable $at): void
    {
        Tournament::query()->where('id', $tournamentId)->update([
            'date' => $at->toDateString(),
            'created_at' => $at->subDays(10),
            'updated_at' => $at,
        ]);

        $gameIds = Game::query()->where('tournament_id', $tournamentId)->pluck('id');
        $playoffIds = PlayoffGame::query()->where('tournament_id', $tournamentId)->pluck('id');

        Game::query()->where('tournament_id', $tournamentId)->update([
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        PlayoffGame::query()->where('tournament_id', $tournamentId)->update([
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        GroupStanding::query()->where('tournament_id', $tournamentId)->update([
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        GameLeg::query()
            ->where(function ($q) use ($gameIds, $playoffIds) {
                $q->whereIn('game_id', $gameIds)->orWhereIn('playoff_game_id', $playoffIds);
            })
            ->update([
                'started_at' => $at->subMinutes(90),
                'finished_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
    }

    private function recordH2hSnapshotsForTournament(int $tournamentId, PlayerCareerSnapshotService $snapshotService): void
    {
        $games = Game::query()->where('tournament_id', $tournamentId)->where('status', GameStatus::FINISHED)->get();
        foreach ($games as $game) {
            $snapshotService->recordFinishedH2h(GameScoringContext::fromGroupGame($game), $game);
        }

        $playoff = PlayoffGame::query()
            ->where('tournament_id', $tournamentId)
            ->where('status', GameStatus::FINISHED)
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->get();
        foreach ($playoff as $game) {
            $snapshotService->recordFinishedH2h(GameScoringContext::fromPlayoffGame($game), $game);
        }
    }

    /**
     * @param  list<Player>  $guests
     */
    private function seedLeague(Organization $organization, User $admin, User $careerUser, Player $career, array $guests): void
    {
        $leagueService = app(LeagueService::class);
        $seasonService = app(LeagueSeasonService::class);
        $snapshotService = app(PlayerCareerSnapshotService::class);

        $league = $leagueService->create($organization->id, self::LEAGUE_NAME, 'Round-robin 8 graczy pod profil demo.', [
            [
                'name' => 'Liga klubowa',
                'capacity' => 8,
                'startingScore' => 501,
                'legsToWinSet' => 2,
                'setsToWinMatch' => 1,
                'promoteDirect' => 0,
                'promotePlayoff' => 0,
            ],
        ]);

        $leagueService->addRelatedUser($league->id, $careerUser->id);

        foreach ($guests as $guest) {
            $guest->forceFill(['league_id' => $league->id])->save();
        }

        $league->refresh();
        $division = $league->divisions->first();
        if ($division === null) {
            throw new RuntimeException('Brak szczebla ligi profilu.');
        }

        $leagueService->assignPlayer($league->id, $division->id, (int) $career->id);
        foreach ($guests as $guest) {
            $leagueService->assignPlayer($league->id, $division->id, (int) $guest->id);
        }

        $season = $seasonService->create(
            $league->id,
            '25/26',
            'deadline',
            1,
            '2025-09-15',
            '2026-05-20',
            null,
            true,
        );

        $this->finishLeagueSeason($season);
        $season = $season->fresh('games');

        foreach ($season->games()->where('status', LeagueGameStatus::FINISHED)->get() as $game) {
            /** @var LeagueGame $game */
            DemoGameScoringFactory::seedForLeagueGame($game);
        }

        $this->backdateLeagueGames($season);
        foreach ($season->games()->where('status', LeagueGameStatus::FINISHED)->get() as $game) {
            /** @var LeagueGame $game */
            $snapshotService->recordFinishedH2h(GameScoringContext::fromLeagueGame($game), $game);
        }
    }

    private function finishLeagueSeason(LeagueSeason $season): void
    {
        $service = app(LeagueSeasonService::class);
        for ($step = 0; $step < 16; $step++) {
            $season->refresh();
            if ($season->status === LeagueSeasonStatus::FINISHED) {
                return;
            }
            $this->completeLeagueGames($season);
            $season->refresh();
            if ($season->status === LeagueSeasonStatus::FINISHED) {
                return;
            }
            try {
                $service->advance($season->id);
            } catch (DomainException) {
                $this->completeLeagueGames($season->fresh());
                $service->advance($season->id);
            }
        }

        $season->refresh();
        if ($season->status !== LeagueSeasonStatus::FINISHED) {
            throw new RuntimeException('Nie udało się zakończyć sezonu ligi profilu (status: '.$season->status->value.').');
        }
    }

    private function completeLeagueGames(LeagueSeason $season): void
    {
        $service = app(LeagueSeasonService::class);
        $games = $season->games()->where('status', LeagueGameStatus::SCHEDULED->value)->get();
        foreach ($games as $game) {
            /** @var LeagueGame $game */
            $hash = $this->u($season->name.'-'.$game->id);
            $player1Wins = ($hash % 5) !== 0;
            $loserLegs = ($hash % 4) === 0 ? 1 : 0;
            $service->recordResult(
                (int) $game->id,
                $player1Wins ? 2 : $loserLegs,
                $player1Wins ? $loserLegs : 2,
            );
        }
    }

    private function backdateLeagueGames(LeagueSeason $season): void
    {
        $season->loadMissing('games.matchday');
        foreach ($season->games as $game) {
            $at = $game->matchday?->window_start
                ? CarbonImmutable::parse($game->matchday->window_start)->setTime(20, 15)
                : CarbonImmutable::parse('2025-11-01 20:15:00');
            $game->forceFill([
                'created_at' => $at,
                'updated_at' => $at,
            ])->save();

            GameLeg::query()->where('league_game_id', $game->id)->update([
                'started_at' => $at->subMinutes(80),
                'finished_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    private function trainingGameType(int $h): string
    {
        return match ($h % 12) {
            0 => MatchFormat::GAME_TYPE_CRICKET,
            1 => MatchFormat::GAME_TYPE_BOB27,
            2 => MatchFormat::GAME_TYPE_ATC,
            3 => MatchFormat::GAME_TYPE_CATCH40,
            4 => MatchFormat::GAME_TYPE_CRICKET56,
            default => MatchFormat::DEFAULT_GAME_TYPE,
        };
    }

    private function quickGameType(int $h): string
    {
        return match ($h % 14) {
            0 => MatchFormat::GAME_TYPE_CRICKET,
            1 => MatchFormat::GAME_TYPE_BOB27,
            2 => MatchFormat::GAME_TYPE_ATC,
            3 => MatchFormat::GAME_TYPE_CATCH40,
            4 => MatchFormat::GAME_TYPE_CRICKET56,
            default => MatchFormat::DEFAULT_GAME_TYPE,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFor(string $gameType): array
    {
        return match ($gameType) {
            MatchFormat::GAME_TYPE_BOB27 => [
                'gameType' => $gameType,
                'bob27Mode' => MatchFormat::BOB27_MODE_HARD,
                'bob27Bull' => MatchFormat::BOB27_BULL_WITH,
            ],
            MatchFormat::DEFAULT_GAME_TYPE => [
                'gameType' => $gameType,
                'startingScore' => 501,
                'legsToWinSet' => 2,
                'setsToWinMatch' => 1,
            ],
            default => ['gameType' => $gameType],
        };
    }

    private function u(int|string $seed): int
    {
        return (int) sprintf('%u', crc32((string) $seed));
    }

    private function printReady(?Player $player): void
    {
        $playerId = $player?->id ?? '?';
        $this->command?->info('Gracz profilu demo (rok historii):');
        $this->command?->line('  '.self::PLAYER_NAME);
        $this->command?->line('  '.self::EMAIL.' / '.self::PASSWORD);
        $this->command?->line('  player_id: '.$playerId);
        if (is_int($playerId)) {
            $this->command?->line('  Profil web: '.url('/players/'.$playerId));
        }
    }
}
