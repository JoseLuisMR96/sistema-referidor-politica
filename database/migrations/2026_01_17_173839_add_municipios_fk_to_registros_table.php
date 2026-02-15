<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Crear columnas solo si NO existen
        Schema::table('public_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('public_registrations', 'residence_municipality_id')) {
                $table->foreignId('residence_municipality_id')->nullable();
            }

            if (!Schema::hasColumn('public_registrations', 'voting_municipality_id')) {
                $table->foreignId('voting_municipality_id')->nullable();
            }
        });

        // 2) Crear FKs con nombre fijo (evita duplicados raros y permite rollback limpio)
        Schema::table('public_registrations', function (Blueprint $table) {
            // Nota: si la FK ya existiera con otro nombre, aquí puede fallar.
            // Si te falla, te digo el query exacto para ver el nombre y lo ajustamos.
            $table->foreign('residence_municipality_id', 'pr_residence_muni_fk')
                ->references('id')->on('municipios')
                ->nullOnDelete();

            $table->foreign('voting_municipality_id', 'pr_voting_muni_fk')
                ->references('id')->on('municipios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('public_registrations', function (Blueprint $table) {
            // Dropea constraints por nombre fijo
            try { $table->dropForeign('pr_residence_muni_fk'); } catch (\Throwable $e) {}
            try { $table->dropForeign('pr_voting_muni_fk'); } catch (\Throwable $e) {}

            // Si quieres eliminar columnas también, descomenta:
            // if (Schema::hasColumn('public_registrations', 'residence_municipality_id')) $table->dropColumn('residence_municipality_id');
            // if (Schema::hasColumn('public_registrations', 'voting_municipality_id')) $table->dropColumn('voting_municipality_id');
        });
    }
};
