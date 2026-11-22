<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_favorites', function (Blueprint $table) {
            $table->string('variant', 32)->default('')->after('exercise_key');
        });

        // Stare unikalne (user_id, exercise_key) blokuje dwa warianty tego samego ćwiczenia.
        Schema::table('training_favorites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'exercise_key']);
        });

        // MySQL: wiele NULL w exercise_key (plany/wyzwania) jest OK przy partial uniqueness —
        // trzymając variant='' dla ćwiczeń bez wariantu.
        Schema::table('training_favorites', function (Blueprint $table) {
            $table->unique(['user_id', 'exercise_key', 'variant'], 'training_favorites_user_exercise_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('training_favorites', function (Blueprint $table) {
            $table->dropUnique('training_favorites_user_exercise_variant_unique');
        });

        // Przed przywróceniem starego unique zostaw po jednym wierszu na exercise_key.
        $dupes = DB::table('training_favorites')
            ->whereNotNull('exercise_key')
            ->select('user_id', 'exercise_key')
            ->groupBy('user_id', 'exercise_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($dupes as $dupe) {
            $ids = DB::table('training_favorites')
                ->where('user_id', $dupe->user_id)
                ->where('exercise_key', $dupe->exercise_key)
                ->orderBy('id')
                ->pluck('id');
            $keep = $ids->shift();
            if ($ids->isNotEmpty()) {
                DB::table('training_favorites')->whereIn('id', $ids->all())->delete();
            }
            unset($keep);
        }

        Schema::table('training_favorites', function (Blueprint $table) {
            $table->unique(['user_id', 'exercise_key']);
            $table->dropColumn('variant');
        });
    }
};
