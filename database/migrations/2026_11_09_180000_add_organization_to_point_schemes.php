<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schemat systemowy: organization_id null.
 * Kopia organizacji: organization_id ustawione, is_active = aktualna tabela.
 * Stare wersje zostają is_active = false, dopóki wskazuje na nie turniej.
 * FK bez nullOnDelete — null oznacza schemat systemowy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_schemes', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('max_players');
            $table->index(['organization_id', 'is_active', 'min_players'], 'point_schemes_org_active_min_idx');
        });
    }

    public function down(): void
    {
        Schema::table('point_schemes', function (Blueprint $table) {
            $table->dropIndex('point_schemes_org_active_min_idx');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('is_active');
        });
    }
};
