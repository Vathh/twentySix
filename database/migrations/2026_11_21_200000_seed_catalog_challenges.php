<?php

use App\Domain\Training\TrainingChallengeCatalog;
use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (TrainingChallengeCatalog::all() as $key => $meta) {
            $existing = DB::table('training_definitions')
                ->where('kind', TrainingKind::Challenge->value)
                ->where('mechanic', $key)
                ->whereNotIn('status', [
                    TrainingDefinitionStatus::Cancelled->value,
                    TrainingDefinitionStatus::Removed->value,
                ])
                ->orderBy('id')
                ->first();

            if ($existing !== null) {
                DB::table('training_definitions')->where('id', $existing->id)->update([
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                    'badge_thresholds' => $existing->badge_thresholds
                        ?: json_encode(TrainingChallengeCatalog::defaultBadgeThresholds($key)),
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('training_definitions')->insert([
                'kind' => TrainingKind::Challenge->value,
                'status' => TrainingDefinitionStatus::Active->value,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'image_path' => null,
                'difficulty' => null,
                'declared_minutes' => null,
                'mechanic' => $key,
                'badge_thresholds' => json_encode(TrainingChallengeCatalog::defaultBadgeThresholds($key)),
                'duration_seconds' => null,
                'dart_count' => null,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Katalogowych wyzwań nie kasujemy w down — mogą mieć sesje i ranking.
    }
};
