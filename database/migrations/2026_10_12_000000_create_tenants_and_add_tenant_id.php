<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant: cada candidato/campaña es un tenant y todos sus datos llevan
 * tenant_id.
 *
 * 1. Crea `tenants` y un "Tenant inicial" que recibe TODOS los datos que ya
 *    existían (se renombra después desde la pantalla de tenants).
 * 2. Agrega tenant_id a cada tabla de datos, lo rellena y lo deja NOT NULL.
 * 3. Los únicos que eran globales pasan a ser por tenant: la misma cédula
 *    puede registrarse con dos candidatos distintos.
 * 4. users.tenant_id queda NULLABLE: nulo = usuario global.
 *
 * Se dejan fuera a propósito: municipios/departamentos (catálogo común),
 * call_results (el webhook de Retell no dice de qué tenant es),
 * campaign_metrics (cuelga de campaigns) y campaign_batches (sin uso).
 */
return new class extends Migration
{
    /** Tablas que pasan a pertenecer a un tenant. */
    private const TABLAS = [
        'referrers',
        'public_registrations',
        'referidores_pregoneros',
        'referidos_pregoneros',
        'imported_people',
        'campaigns',
        'campaign_messages',
        'whatsapp_campaign_responses',
        'whatsapp_outbox',
        'wpp_campaigns',
        'wpp_contacts',
        'wpp_messages',
    ];

    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable(); // p. ej. "Postulado al Concejo municipal"
            $table->string('slug', 160)->unique();
            $table->boolean('activo')->default(true);
            $table->string('twilio_messaging_service_sid', 64)->nullable();
            $table->timestamps();
        });

        $inicial = DB::table('tenants')->insertGetId([
            'nombre' => 'Tenant inicial',
            'descripcion' => 'Datos existentes antes de separar por tenant',
            'slug' => 'tenant-inicial',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'tenant_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            });

            DB::table($tabla)->update(['tenant_id' => $inicial]);

            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        // La cédula es única por tenant, no global
        Schema::table('public_registrations', function (Blueprint $table) {
            $table->dropUnique('uniq_doc_type_number');
            $table->unique(['tenant_id', 'document_type', 'document_number'], 'uniq_tenant_doc');
        });

        // La misma persona puede estar en el padrón de dos candidatos
        Schema::table('imported_people', function (Blueprint $table) {
            $table->dropUnique(['document_number']);
            $table->unique(['tenant_id', 'document_number']);
        });

        if (Schema::hasTable('wpp_contacts')) {
            Schema::table('wpp_contacts', function (Blueprint $table) {
                $table->dropUnique(['phone']);
                $table->unique(['tenant_id', 'phone']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            // Nulo = usuario global (ve todos los tenants)
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        if (Schema::hasTable('wpp_contacts')) {
            Schema::table('wpp_contacts', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'phone']);
                $table->unique('phone');
            });
        }

        Schema::table('imported_people', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'document_number']);
            $table->unique('document_number');
        });

        Schema::table('public_registrations', function (Blueprint $table) {
            $table->dropUnique('uniq_tenant_doc');
            $table->unique(['document_type', 'document_number'], 'uniq_doc_type_number');
        });

        foreach (array_reverse(self::TABLAS) as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'tenant_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('tenant_id');
                });
            }
        }

        Schema::dropIfExists('tenants');
    }
};
