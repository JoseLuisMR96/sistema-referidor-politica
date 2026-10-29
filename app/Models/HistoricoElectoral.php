<?php

namespace App\Models;

use App\Enums\CargoEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado electoral histórico: votos de un candidato o lista en un lugar.
 * Dato público y global (sin tenant): ver la migración de la tabla.
 */
class HistoricoElectoral extends Model
{
    protected $table = 'historico_electoral';

    protected $fillable = [
        'anio', 'cargo', 'vuelta', 'departamento_id', 'municipio_id', 'comuna',
        'partido', 'candidato', 'votos', 'elegido', 'curules',
    ];

    protected $casts = [
        'cargo' => CargoEnum::class,
        'elegido' => 'boolean',
        'votos' => 'integer',
        'curules' => 'integer',
    ];

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
