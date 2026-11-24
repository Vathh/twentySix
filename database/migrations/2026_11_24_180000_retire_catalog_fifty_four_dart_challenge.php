<?php

use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Usuwa gotowe wyzwanie 54-Dart z katalogu — na produkcji składa się je z trzech ćwiczeń.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('training_definitions')
            ->where('kind', TrainingKind::Challenge->value)
            ->where('mechanic', 'fifty_four_dart')
            ->whereNotIn('status', [
                TrainingDefinitionStatus::Cancelled->value,
                TrainingDefinitionStatus::Removed->value,
            ])
            ->update([
                'status' => TrainingDefinitionStatus::Cancelled->value,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Nie przywracamy automatycznie — treść była katalogowa.
    }
};
