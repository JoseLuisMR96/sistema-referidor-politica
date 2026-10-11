<?php

use App\Enums\CampaignStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sincroniza el ENUM de `campaigns.status` con CampaignStatusEnum.
 *
 * La migración 2026_03_17_expand_message_status_enum dejó el ENUM sin los
 * valores 'draft', 'batched' ni 'sending', que son justamente los que escriben
 * CreateCampaignAction y CampaignRateLimiter: crear una campaña fallaba (o el
 * estado se guardaba vacío) en cualquier base ya migrada.
 *
 * Esa migración ya quedó corregida para instalaciones nuevas; esta arregla las
 * bases de datos existentes.
 */
return new class extends Migration
{
    /** @return list<string> */
    private function campaignStatuses(): array
    {
        $legacy = ['active', 'pending', 'resumed'];

        $actuales = array_map(
            fn (CampaignStatusEnum $case) => $case->value,
            CampaignStatusEnum::cases(),
        );

        return array_values(array_unique([...$actuales, ...$legacy]));
    }

    private function esMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function up(): void
    {
        if (! $this->esMysql() || ! Schema::hasTable('campaigns')) {
            return;
        }

        $lista = "'".implode("','", $this->campaignStatuses())."'";

        // Las filas que quedaron con status vacío por el ENUM incompleto
        DB::statement("UPDATE `campaigns` SET `status` = 'draft' WHERE `status` IS NULL OR `status` = ''");

        DB::statement("ALTER TABLE `campaigns` MODIFY COLUMN `status` ENUM({$lista}) DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (! $this->esMysql() || ! Schema::hasTable('campaigns')) {
            return;
        }

        DB::statement("ALTER TABLE `campaigns` MODIFY COLUMN `status` VARCHAR(255) DEFAULT 'queued'");
    }
};
