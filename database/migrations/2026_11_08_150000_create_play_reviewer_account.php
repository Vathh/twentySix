<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Konto recenzenta Google Play. Hasło jest wyłącznie hashem — treść zna tylko osoba wrzucająca aplikację do sklepu.
 * W testach konta nie tworzymy, żeby nie zmieniać pustej bazy każdego testu.
 */
return new class extends Migration
{
    private const EMAIL = 'play-review@twentysix.pl';

    private const PASSWORD_HASH = '$2y$10$9pWyEIXY7j0rjnpbWpL3kO02.Xm7RINBHzZgC4HNZW7GSqQ0nxC/W';

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $now = now();
        $userId = DB::table('users')->where('email', self::EMAIL)->value('id');

        if ($userId === null) {
            $userId = DB::table('users')->insertGetId([
                'email' => self::EMAIL,
                'password' => self::PASSWORD_HASH,
                'email_verified_at' => $now,
                'terms_accepted_at' => $now,
                'profile_feed_visibility' => 'public',
                'role' => 'user',
                'can_create_organizations' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('users')->where('id', $userId)->update([
                'email_verified_at' => DB::raw('COALESCE(email_verified_at, CURRENT_TIMESTAMP)'),
                'terms_accepted_at' => DB::raw('COALESCE(terms_accepted_at, CURRENT_TIMESTAMP)'),
                'updated_at' => $now,
            ]);
        }

        $hasPlayer = DB::table('players')->where('user_id', $userId)->exists();
        if (! $hasPlayer) {
            DB::table('players')->insert([
                'user_id' => $userId,
                'name' => 'Recenzent Play',
                'is_bye' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $userId = DB::table('users')->where('email', self::EMAIL)->value('id');
        if ($userId === null) {
            return;
        }

        DB::table('players')->where('user_id', $userId)->delete();
        DB::table('users')->where('id', $userId)->delete();
    }
};
