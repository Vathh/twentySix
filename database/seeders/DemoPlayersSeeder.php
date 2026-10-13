<?php

namespace Database\Seeders;

use App\Domain\Badge\BadgeCategory;
use App\Models\Badge\PlayerBadge;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Friends\FriendshipRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Konta testowe do mobilki (logowanie + znajomi).
 * Idempotentny — bezpieczny przy ponownym uruchomieniu seeda.
 */
class DemoPlayersSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /**
     * @var list<array{email: string, name: string}>
     */
    private const PLAYERS = [
        ['email' => 'gracz1@test.pl', 'name' => 'Jan Kowalski'],
        ['email' => 'gracz2@test.pl', 'name' => 'Anna Nowak'],
        ['email' => 'gracz3@test.pl', 'name' => 'Piotr Wiśniewski'],
        ['email' => 'gracz4@test.pl', 'name' => 'Maria Wójcik'],
        ['email' => 'gracz5@test.pl', 'name' => 'Tomasz Kamiński'],
        ['email' => 'gracz6@test.pl', 'name' => 'Katarzyna Lewandowska'],
        ['email' => 'gracz7@test.pl', 'name' => 'Marcin Zieliński'],
        ['email' => 'gracz8@test.pl', 'name' => 'Magdalena Szymańska'],
    ];

    /**
     * Podgląd pasków: różne stopnie i wypełnienie (0 = brak wiersza, medal zablokowany).
     *
     * @var array<string, array<string, int>>
     */
    private const HIGH_VISITS = [
        'gracz1@test.pl' => ['100' => 32, '140' => 12, '180' => 2],
        'gracz2@test.pl' => ['100' => 4],
        'gracz3@test.pl' => ['100' => 400, '140' => 80, '180' => 40],
        'gracz4@test.pl' => ['100' => 1, '140' => 1, '180' => 1],
        'gracz5@test.pl' => ['100' => 9, '140' => 24, '180' => 9],
        'gracz6@test.pl' => ['100' => 60, '140' => 6, '180' => 250],
        'gracz7@test.pl' => ['100' => 1200, '140' => 3],
        'gracz8@test.pl' => ['100' => 50, '140' => 100, '180' => 50],
    ];

    /**
     * Zdobyte QF (9–19). Brak klucza = odznaka nie pokazuje się na profilu.
     * Progi: 19–16 → 1/3/10/25/50, 15–13 → 1/2/5/10/25, 12–10 → 1/2/3/5/7, 9 → 1/2/3/4/5.
     *
     * @var array<string, array<string, int>>
     */
    private const QF = [
        'gracz1@test.pl' => ['9' => 3, '12' => 2, '15' => 5, '18' => 1, '19' => 10],
        'gracz2@test.pl' => ['19' => 1],
        'gracz3@test.pl' => ['9' => 5, '11' => 7, '14' => 25, '17' => 50],
        'gracz4@test.pl' => ['16' => 1],
        'gracz5@test.pl' => ['13' => 4, '10' => 3],
        'gracz6@test.pl' => ['10' => 5, '16' => 25],
        'gracz8@test.pl' => ['9' => 1, '19' => 3],
    ];

    public function run(): void
    {
        $users = $this->ensureUsers();
        $this->seedFriendships($users);
        $this->seedInvitations($users);
        $this->seedHighVisitBadges($users);
        $this->seedQfBadges($users);

        $this->command?->info('DemoPlayersSeeder: 8 kont (gracz1@test.pl … gracz8@test.pl), hasło: password');
        $jan = $users['gracz1@test.pl']->player;
        if ($jan !== null) {
            $this->command?->line('  odznaki Jana Kowalskiego: '.url('/players/'.$jan->id));
        }
    }

    /**
     * @return array<string, User> email => User
     */
    private function ensureUsers(): array
    {
        $map = [];

        foreach (self::PLAYERS as $row) {
            $user = User::query()->firstOrCreate(
                ['email' => $row['email']],
                [
                    'password' => self::PASSWORD,
                ],
            );

            // email_verified_at nie jest w User::$fillable — firstOrCreate by go pominął.
            // Demo konta zawsze traktujemy jako zweryfikowane.
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

            Player::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $row['name'],
                    'organization_id' => null,
                    'season_id' => null,
                ],
            );

            $map[$row['email']] = $user->fresh(['player']);
        }

        return $map;
    }

    /**
     * Zaakceptowane znajomości (symetrycznie w API — w bazie jeden wiersz A→B).
     *
     * @param  array<string, User>  $users
     */
    private function seedFriendships(array $users): void
    {
        $repo = app(FriendshipRepository::class);
        $id = fn (int $n) => $users['gracz'.$n.'@test.pl']->id;

        $pairs = [
            [1, 2], [1, 3], [1, 4],
            [2, 3],
            [3, 5],
            [4, 6],
            [5, 7],
            [6, 8],
            [7, 8],
        ];

        foreach ($pairs as [$a, $b]) {
            $repo->addFriendship($id($a), $id($b));
        }
    }

    /**
     * Zaproszenia w różnych stanach (do testów panelu znajomych).
     *
     * @param  array<string, User>  $users
     */
    private function seedInvitations(array $users): void
    {
        $id = fn (int $n) => $users['gracz'.$n.'@test.pl']->id;
        $now = now();

        $pending = [
            [1, 5], // Jan → Tomasz (Tomasz: otrzymane)
            [2, 6], // Anna → Katarzyna
            [7, 1], // Marcin → Jan (Jan: otrzymane)
            [4, 8], // Maria → Magdalena
        ];

        foreach ($pending as [$sender, $receiver]) {
            DB::table('friendship_invitations')->updateOrInsert(
                [
                    'sender_id' => $id($sender),
                    'receiver_id' => $id($receiver),
                ],
                [
                    'status' => 'pending',
                    'responded_at' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        // Odrzucone (np. Piotr → Magdalena) — nie powinno blokować nowego zaproszenia w przyszłości po usunięciu
        DB::table('friendship_invitations')->updateOrInsert(
            [
                'sender_id' => $id(3),
                'receiver_id' => $id(8),
            ],
            [
                'status' => 'rejected',
                'responded_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        // Wysłane i już zaakceptowane (historyczne) — 5 → 6, potem znajomi z seedFriendships nie łączą 5-6
        // 5 i 6 nie są znajomymi w pairs — do testu accept flow można użyć pending 2→6
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedHighVisitBadges(array $users): void
    {
        $category = BadgeCategory::HighVisit->value;
        $now = now();

        foreach (self::HIGH_VISITS as $email => $hits) {
            $player = $users[$email]->player ?? null;
            if ($player === null) {
                continue;
            }

            PlayerBadge::query()
                ->where('player_id', $player->id)
                ->where('category', $category)
                ->delete();

            foreach ($hits as $key => $times) {
                if ($times < 1) {
                    continue;
                }

                PlayerBadge::query()->create([
                    'player_id' => $player->id,
                    'category' => $category,
                    'badge_key' => $key,
                    'times_earned' => $times,
                    'first_unlocked_at' => $now->copy()->subDays(30),
                    'last_earned_at' => $now,
                    'source_quality' => 'manual',
                ]);
            }
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedQfBadges(array $users): void
    {
        $category = BadgeCategory::Qf->value;
        $now = now();

        foreach (self::QF as $email => $hits) {
            $player = $users[$email]->player ?? null;
            if ($player === null) {
                continue;
            }

            PlayerBadge::query()
                ->where('player_id', $player->id)
                ->where('category', $category)
                ->delete();

            foreach ($hits as $key => $times) {
                if ($times < 1) {
                    continue;
                }

                PlayerBadge::query()->create([
                    'player_id' => $player->id,
                    'category' => $category,
                    'badge_key' => $key,
                    'times_earned' => $times,
                    'first_unlocked_at' => $now->copy()->subDays(14),
                    'last_earned_at' => $now,
                    'source_quality' => 'manual',
                ]);
            }
        }
    }
}
