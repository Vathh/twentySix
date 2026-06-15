<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Naprawa schematu quick_game_results — starsze bazy mogły mieć tabelę-szkielet
 * bez quick_game_id / player_id / score / place (tylko average itd.).
 */
return new class extends Migration
{
    private function createFullTable(): void
    {
        Schema::create('quick_game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_game_id')->constrained('quick_games')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->unsignedTinyInteger('place')->default(0);
            $table->decimal('average', 8, 2)->nullable();
            $table->unsignedSmallInteger('darts_thrown')->nullable();
            $table->unsignedSmallInteger('points_earned')->nullable();
            $table->timestamps();
        });
    }

    /** @return list<string> */
    private function requiredColumns(): array
    {
        return [
            'quick_game_id',
            'player_id',
            'score',
            'place',
            'average',
            'darts_thrown',
            'points_earned',
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('quick_game_results')) {
            $this->createFullTable();

            return;
        }

        $missingCore = collect($this->requiredColumns())
            ->contains(fn (string $column) => ! Schema::hasColumn('quick_game_results', $column));

        if ($missingCore && DB::table('quick_game_results')->count() === 0) {
            Schema::drop('quick_game_results');
            $this->createFullTable();

            return;
        }

        Schema::table('quick_game_results', function (Blueprint $table) {
            if (! Schema::hasColumn('quick_game_results', 'quick_game_id')) {
                $table->foreignId('quick_game_id')->constrained('quick_games')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('quick_game_results', 'player_id')) {
                $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('quick_game_results', 'score')) {
                $table->unsignedTinyInteger('score')->default(0);
            }
            if (! Schema::hasColumn('quick_game_results', 'place')) {
                $table->unsignedTinyInteger('place')->default(0);
            }
            if (! Schema::hasColumn('quick_game_results', 'average')) {
                $table->decimal('average', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('quick_game_results', 'darts_thrown')) {
                $table->unsignedSmallInteger('darts_thrown')->nullable();
            }
            if (! Schema::hasColumn('quick_game_results', 'points_earned')) {
                $table->unsignedSmallInteger('points_earned')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Schemat naprawczy — brak automatycznego rollbacku.
    }
};
