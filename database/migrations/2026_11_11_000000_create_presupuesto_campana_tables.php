<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presupuesto de campaña por candidato: tope legal de gastos (lo fija el CNE
 * por circunscripción), presupuesto por categoría de Cuentas Claras y gastos
 * con su soporte. El dinero de los eventos de campaña se suma aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants');
            $table->decimal('tope_legal', 16, 2)->nullable();
            $table->string('tope_fuente', 190)->nullable(); // p. ej. "Resolución CNE 0000 de 2027"
            $table->timestamps();
        });

        Schema::create('presupuesto_rubros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('categoria', 10);
            $table->decimal('monto', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'categoria']);
        });

        Schema::create('gastos_campana', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('categoria', 10);
            $table->date('fecha');
            $table->string('concepto', 190);
            $table->string('proveedor', 150)->nullable();
            $table->string('documento', 60)->nullable(); // número de factura o soporte
            $table->decimal('valor', 14, 2);
            $table->string('estado', 20)->default('pagado'); // pagado | por_pagar
            $table->foreignId('evento_id')->nullable()->constrained('eventos_campana')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('equipo_campana')->nullOnDelete();
            $table->string('soporte_ruta', 255)->nullable();
            $table->string('soporte_nombre', 190)->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'fecha']);
            $table->index(['tenant_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos_campana');
        Schema::dropIfExists('presupuesto_rubros');
        Schema::dropIfExists('presupuestos');
    }
};
