<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Renombrar whatsapp_campaigns → campaigns PRIMERO
        if (Schema::hasTable('whatsapp_campaigns') && !Schema::hasTable('campaigns')) {
            Schema::rename('whatsapp_campaigns', 'campaigns');
        }

        // 2. Renombrar whatsapp_messages → campaign_messages
        if (Schema::hasTable('whatsapp_messages') && !Schema::hasTable('campaign_messages')) {
            Schema::rename('whatsapp_messages', 'campaign_messages');
        }

        // 3. Agregar nuevas columnas a campaign_messages (si no existen)
        if (Schema::hasTable('campaign_messages')) {
            Schema::table('campaign_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('campaign_messages', 'referrer_id')) {
                    $table->foreignId('referrer_id')
                        ->nullable()
                        ->constrained('referrers')
                        ->nullableOnDelete();
                }

                if (!Schema::hasColumn('campaign_messages', 'referidor_pregonero_id')) {
                    $table->foreignId('referidor_pregonero_id')
                        ->nullable()
                        ->constrained('referidores_pregoneros')
                        ->nullableOnDelete();
                }

                if (!Schema::hasColumn('campaign_messages', 'source_type')) {
                    $table->enum('source_type', ['twilio', 'wppconnect'])
                        ->default('twilio')
                        ->after('status');
                }

                if (!Schema::hasColumn('campaign_messages', 'provider_message_id')) {
                    $table->string('provider_message_id', 100)->nullable();
                }
            });

            // Add indexes separately to avoid duplication
            DB::statement('ALTER TABLE `campaign_messages` ADD INDEX IF NOT EXISTS `campaign_messages_referrer_id_index` (`referrer_id`)');
            DB::statement('ALTER TABLE `campaign_messages` ADD INDEX IF NOT EXISTS `campaign_messages_referidor_pregonero_id_index` (`referidor_pregonero_id`)');
        }

        // 4. Agregar columnas a campaigns (si no existen)
        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (!Schema::hasColumn('campaigns', 'source')) {
                    $table->enum('source', ['twilio', 'wppconnect'])
                        ->default('twilio')
                        ->after('status');
                }

                if (!Schema::hasColumn('campaigns', 'referrer_id')) {
                    $table->foreignId('referrer_id')
                        ->nullable()
                        ->constrained('referrers')
                        ->nullableOnDelete();
                }

                if (!Schema::hasColumn('campaigns', 'referidor_pregonero_id')) {
                    $table->foreignId('referidor_pregonero_id')
                        ->nullable()
                        ->constrained('referidores_pregoneros')
                        ->nullableOnDelete();
                }

                if (!Schema::hasColumn('campaigns', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
            });

            // Add indexes separately
            DB::statement('ALTER TABLE `campaigns` ADD INDEX IF NOT EXISTS `campaigns_referrer_id_index` (`referrer_id`)');
            DB::statement('ALTER TABLE `campaigns` ADD INDEX IF NOT EXISTS `campaigns_referidor_pregonero_id_index` (`referidor_pregonero_id`)');
        }

        // NOTE: Foreign key campaign_messages.campaign_id → campaigns.id was automatically 
        // renamed by MySQL when the tables were renamed. It already exists and does not need to be created again.

        // 6. Crear campaign_batches (skip if exists)
        if (!Schema::hasTable('campaign_batches')) {
            Schema::create('campaign_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')
                    ->constrained('campaigns')
                    ->cascadeOnDelete();
                $table->integer('batch_number');
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                    ->default('pending');
                $table->integer('messages_count');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->integer('retry_count')->default(0);
                $table->integer('max_retries')->default(3);
                $table->timestamps();

                $table->index('campaign_id');
                $table->index('status');
                $table->index(['campaign_id', 'status']);
            });
        }

        // 7. Crear whatsapp_campaign_responses (skip if exists)
        if (!Schema::hasTable('whatsapp_campaign_responses')) {
            Schema::create('whatsapp_campaign_responses', function (Blueprint $table) {
                $table->id();

                $table->foreignId('campaign_id')
                    ->constrained('campaigns')
                    ->cascadeOnDelete();

                $table->foreignId('campaign_message_id')
                    ->constrained('campaign_messages')
                    ->cascadeOnDelete();

                $table->string('phone', 20);

                $table->foreignId('referrer_id')
                    ->nullable()
                    ->constrained('referrers')
                    ->nullableOnDelete();

                $table->foreignId('referidor_pregonero_id')
                    ->nullable()
                    ->constrained('referidores_pregoneros')
                    ->nullableOnDelete();

                $table->string('button_id', 50);
                $table->string('button_text', 255)->nullable();

                $table->timestamp('response_timestamp');
                $table->string('twilio_message_sid', 100)->nullable();
                $table->json('raw_webhook');

                $table->timestamp('processed_at')->nullable();
                $table->enum('processing_status', ['pending', 'processed', 'error'])
                    ->default('pending');
                $table->text('processing_error')->nullable();

                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();

                $table->timestamps();

                // Índices
                $table->index('campaign_id');
                $table->index('referrer_id');
                $table->index('referidor_pregonero_id');
                $table->index('button_id');
                $table->index('response_timestamp');
                $table->index('processing_status');

                // Prevenir duplicados
                $table->unique([
                    'campaign_id',
                    'campaign_message_id',
                    'phone',
                    'button_id',
                ], 'unique_campaign_response');
            });
        }

        // 8. Crear campaign_metrics (caché denormalizado)
        if (!Schema::hasTable('campaign_metrics')) {
            Schema::create('campaign_metrics', function (Blueprint $table) {
                $table->id();

                $table->foreignId('campaign_id')
                    ->unique()
                    ->constrained('campaigns')
                    ->cascadeOnDelete();

                // Totales
                $table->integer('total_messages')->default(0);
                $table->integer('sent_count')->default(0);
                $table->integer('delivered_count')->default(0);
                $table->integer('failed_count')->default(0);

                // Respuestas de botones
                $table->integer('total_responses')->default(0);
                $table->integer('palom_count')->default(0);
                $table->integer('cepeda_count')->default(0);
                $table->integer('otro_count')->default(0);

                // Tasas (porcentajes)
                $table->decimal('delivery_rate', 5, 2)->default(0);
                $table->decimal('response_rate', 5, 2)->default(0);

                // Timings
                $table->integer('avg_delivery_time_seconds')->nullable();
                $table->integer('fastest_delivery_seconds')->nullable();
                $table->integer('slowest_delivery_seconds')->nullable();

                $table->timestamp('updated_at');

                $table->index('campaign_id');
            });
        }
    }

    public function down(): void
    {
        // Orden inverso al up()

        // 1. Dropear las nuevas tablas
        Schema::dropIfExists('campaign_metrics');
        Schema::dropIfExists('whatsapp_campaign_responses');
        Schema::dropIfExists('campaign_batches');

        // 2. Revertir cambios en campaign_messages (antes de renombrar)
        if (Schema::hasTable('campaign_messages')) {
            Schema::table('campaign_messages', function (Blueprint $table) {
                // Drop foreign keys
                try {
                    $table->dropForeign('campaign_messages_referrer_id_foreign');
                } catch (\Exception $e) {
                }

                try {
                    $table->dropForeign('campaign_messages_referidor_pregonero_id_foreign');
                } catch (\Exception $e) {
                }

                // Drop columns
                if (Schema::hasColumn('campaign_messages', 'referrer_id')) {
                    $table->dropColumn('referrer_id');
                }

                if (Schema::hasColumn('campaign_messages', 'referidor_pregonero_id')) {
                    $table->dropColumn('referidor_pregonero_id');
                }

                if (Schema::hasColumn('campaign_messages', 'source_type')) {
                    $table->dropColumn('source_type');
                }

                if (Schema::hasColumn('campaign_messages', 'provider_message_id')) {
                    $table->dropColumn('provider_message_id');
                }
            });

            // Renombrar de vuelta
            if (Schema::hasTable('campaign_messages') && !Schema::hasTable('whatsapp_messages')) {
                Schema::rename('campaign_messages', 'whatsapp_messages');
            }
        }

        // 3. Revertir cambios en campaigns (antes de renombrar)
        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                // Drop foreign keys
                try {
                    $table->dropForeign('campaigns_referrer_id_foreign');
                } catch (\Exception $e) {
                }

                try {
                    $table->dropForeign('campaigns_referidor_pregonero_id_foreign');
                } catch (\Exception $e) {
                }

                // Drop columns
                if (Schema::hasColumn('campaigns', 'source')) {
                    $table->dropColumn('source');
                }

                if (Schema::hasColumn('campaigns', 'referrer_id')) {
                    $table->dropColumn('referrer_id');
                }

                if (Schema::hasColumn('campaigns', 'referidor_pregonero_id')) {
                    $table->dropColumn('referidor_pregonero_id');
                }

                if (Schema::hasColumn('campaigns', 'completed_at')) {
                    $table->dropColumn('completed_at');
                }
            });

            // Renombrar de vuelta
            if (Schema::hasTable('campaigns') && !Schema::hasTable('whatsapp_campaigns')) {
                Schema::rename('campaigns', 'whatsapp_campaigns');
            }
        }
    }
};

