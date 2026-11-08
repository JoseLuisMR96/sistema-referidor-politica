<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Puesto de votación (catálogo global, como municipios): zona y código dentro
 * del municipio, con el potencial y las mesas del DIVIPOL.
 */
class PuestoVotacion extends Model
{
    protected $table = 'puestos_votacion';

    protected $fillable = ['municipio_id', 'zona', 'codigo', 'nombre', 'comuna', 'potencial', 'potencial_mujeres', 'mesas', 'potencial_anio'];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
