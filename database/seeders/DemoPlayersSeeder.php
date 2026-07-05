<?php

namespace Database\Seeders;

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

    public function run(): void
    {
        $users = $this->ensureUsers();
        $this->seedFriendships($users);
        $this->seedInvitations($users);

        $this->command?->info('DemoPlayersSeeder: 8 kont (gracz1@test.pl … gracz8@test.pl), hasło: password');
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
                    'email_verified_at' => now(),
                ],
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            Player::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $row['name'],
                    'league_id' => null,
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
}
