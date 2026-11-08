<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->string('subject_key');
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_key']);
        });

        $hidden = [];
        foreach (DB::table('feed_mutes')->get() as $mute) {
            $hidden[$mute->user_id.'|'.$mute->subject_type.'|'.$mute->subject_key] = true;
        }

        $now = now();
        foreach (DB::table('friendships')->get() as $friendship) {
            foreach ([[$friendship->user_id, $friendship->friend_id], [$friendship->friend_id, $friendship->user_id]] as [$from, $to]) {
                if (isset($hidden[$from.'|user|'.$to])) {
                    continue;
                }

                DB::table('follows')->insertOrIgnore([
                    'user_id' => $from,
                    'subject_type' => 'user',
                    'subject_key' => (string) $to,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (DB::table('organization_user')->get() as $membership) {
            if (isset($hidden[$membership->user_id.'|organization|'.$membership->organization_id])) {
                continue;
            }

            DB::table('follows')->insertOrIgnore([
                'user_id' => $membership->user_id,
                'subject_type' => 'organization',
                'subject_key' => (string) $membership->organization_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::dropIfExists('feed_mutes');
    }

    public function down(): void
    {
        Schema::create('feed_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->string('subject_key');
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_key']);
        });

        Schema::dropIfExists('follows');
    }
};
