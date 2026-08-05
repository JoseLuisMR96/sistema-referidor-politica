<?php

use App\Enums\CampaignStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Estados válidos de un mensaje (los de Twilio + pending local).
     *
     * @return list<string>
     */
    public static function messageStatuses(): array
    {
        return ['pending', 'queued', 'accepted', 'sent', 'delivered', 'undelivered', 'failed', 'read'];
    }

    /**
     * Estados válidos de una campaña.
     *
     * Se derivan de CampaignStatusEnum para que no se desincronicen, más los
     * valores heredados que puedan existir en filas antiguas.
     *
     * @return list<string>
     */
    public static function campaignStatuses(): array
    {
        $legacy = ['active', 'pending', 'resumed'];

        $actuales = array_map(
            fn(CampaignStatusEnum $case) => $case->value,
            CampaignStatusEnum::cases(),
        );

        return array_values(array_unique([...$actuales, ...$legacy]));
    }

    /**
     * Convierte la columna en ENUM. Solo aplica en MySQL/MariaDB: en sqlite
     * (que es lo que usa la suite de tests) la columna se queda como texto.
     *
     * @param  list<string>  $statuses
     */
    public static function applyEnum(string $table, array $statuses, string $default): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $lista = "'" . implode("','", $statuses) . "'";

        // Normalizar los valores fuera de rango antes de restringir la columna
        DB::statement("UPDATE `{$table}` SET `status` = '{$default}' WHERE `status` NOT IN ({$lista}) OR `status` IS NULL");

        DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `status` ENUM({$lista}) DEFAULT '{$default}'");
    }

    public function up(): void
    {
        self::applyEnum('campaign_messages', self::messageStatuses(), 'pending');
        self::applyEnum('campaigns', self::campaignStatuses(), 'draft');
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (Schema::hasTable('campaign_messages')) {
            DB::statement("ALTER TABLE `campaign_messages` MODIFY COLUMN `status` VARCHAR(255) DEFAULT 'pending'");
        }

        if (Schema::hasTable('campaigns')) {
            DB::statement("ALTER TABLE `campaigns` MODIFY COLUMN `status` VARCHAR(255) DEFAULT 'queued'");
        }
    }
};
