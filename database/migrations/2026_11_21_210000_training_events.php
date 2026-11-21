<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_definitions', function (Blueprint $table) {
            if (! Schema::hasColumn('training_definitions', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('training_definitions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('starts_at');
            }
            if (! Schema::hasColumn('training_definitions', 'attempts_per_window')) {
                $table->unsignedTinyInteger('attempts_per_window')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('training_definitions', 'window_mode')) {
                $table->string('window_mode', 32)->nullable()->after('attempts_per_window');
            }
            if (! Schema::hasColumn('training_definitions', 'window_anchor_date')) {
                $table->date('window_anchor_date')->nullable()->after('window_mode');
            }
            if (! Schema::hasColumn('training_definitions', 'attempt_timeout_hours')) {
                $table->unsignedSmallInteger('attempt_timeout_hours')->nullable()->after('window_anchor_date');
            }
            if (! Schema::hasColumn('training_definitions', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('attempt_timeout_hours');
            }
            if (! Schema::hasColumn('training_definitions', 'closing_at')) {
                $table->timestamp('closing_at')->nullable()->after('paused_at');
            }
            if (! Schema::hasColumn('training_definitions', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('closing_at');
            }
        });

        if (! Schema::hasTable('training_event_attempts')) {
            Schema::create('training_event_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('definition_id')->constrained('training_definitions')->cascadeOnDelete();
                $table->uuid('client_attempt_id');
                $table->string('status', 16);
                $table->string('window_key', 64);
                $table->dateTime('started_at');
                $table->dateTime('expires_at');
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('voided_at')->nullable();
                $table->string('void_reason', 32)->nullable();
                $table->integer('score')->nullable();
                $table->json('stats')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'client_attempt_id']);
                $table->index(['definition_id', 'user_id', 'status'], 'tea_def_user_status');
                $table->index(['definition_id', 'window_key', 'user_id'], 'tea_def_window_user');
            });
        }

        if (! Schema::hasTable('training_event_disqualifications')) {
            Schema::create('training_event_disqualifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('definition_id')->constrained('training_definitions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('disqualified_at');
                $table->dateTime('reinstated_at')->nullable();
                $table->timestamps();

                $table->unique(['definition_id', 'user_id'], 'ted_def_user_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_event_disqualifications');
        Schema::dropIfExists('training_event_attempts');
        Schema::table('training_definitions', function (Blueprint $table) {
            foreach ([
                'starts_at',
                'ends_at',
                'attempts_per_window',
                'window_mode',
                'window_anchor_date',
                'attempt_timeout_hours',
                'paused_at',
                'closing_at',
                'finished_at',
            ] as $column) {
                if (Schema::hasColumn('training_definitions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
