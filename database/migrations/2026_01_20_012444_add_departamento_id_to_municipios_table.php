<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Agrega FK nullable (para poder migrar sin romper)
        Schema::table('municipios', function (Blueprint $table) {
            $table->foreignId('departamento_id')
                ->nullable()
                ->after('id')
                ->constrained('departamentos');
        });

        // 2) Crea departamentos únicos a partir del texto existente
        // Nota: TRIM/UPPER por limpieza básica
        $deps = DB::table('municipios')
            ->selectRaw('UPPER(TRIM(departamento)) as dep')
            ->whereNotNull('departamento')
            ->where('departamento', '!=', '')
            ->groupBy('dep')
            ->pluck('dep');

        foreach ($deps as $depName) {
            $slug = str($depName)->lower()->slug('-')->toString();

            DB::table('departamentos')->updateOrInsert(
                ['nombre' => $depName],
                [
                    'slug' => $slug,
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 3) Actualiza municipios.departamento_id según el nombre
        DB::statement("
            UPDATE municipios m
            JOIN departamentos d
              ON d.nombre = UPPER(TRIM(m.departamento))
            SET m.departamento_id = d.id
            WHERE m.departamento_id IS NULL
        ");

        // 4) Ahora sí: vuelve NOT NULL, ajusta índices, y opcionalmente elimina 'departamento'
        Schema::table('municipios', function (Blueprint $table) {
            $table->foreignId('departamento_id')->nullable(false)->change();
        });

        // OJO: si vas a eliminar la columna antigua, primero elimina el índice viejo
        // porque tenías index(['departamento','nombre'])
        Schema::table('municipios', function (Blueprint $table) {
            $table->dropIndex(['departamento', 'nombre']); // si existe con esos campos
        });

        Schema::table('municipios', function (Blueprint $table) {
            $table->dropColumn('departamento');
        });

        // Nuevo índice usando FK
        Schema::table('municipios', function (Blueprint $table) {
            $table->index(['departamento_id', 'nombre']);
        });
    }

    public function down(): void
    {
        // revertir: volver a crear el campo 'departamento' y eliminar fk
        Schema::table('municipios', function (Blueprint $table) {
            $table->string('departamento', 80)->default('META')->after('id');
        });

        // intenta rellenar el texto desde departamentos
        DB::statement("
            UPDATE municipios m
            JOIN departamentos d ON d.id = m.departamento_id
            SET m.departamento = d.nombre
        ");

        Schema::table('municipios', function (Blueprint $table) {
            $table->dropIndex(['departamento_id', 'nombre']);
            $table->dropConstrainedForeignId('departamento_id');
            $table->index(['departamento', 'nombre']);
        });

        Schema::dropIfExists('departamentos');
    }
};
