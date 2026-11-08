<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Votos de una mesa reportados por un testigo (E-14), con su foto. */
class ReporteMesa extends Model
{
    use BelongsToTenant;

    protected $table = 'reportes_mesa';

    protected $fillable = [
        'tenant_id', 'eleccion_id', 'testigo_id', 'puesto_id', 'mesa', 'votos',
        'blanco', 'nulos', 'no_marcados', 'total', 'foto_ruta', 'observaciones',
    ];

    protected $casts = ['votos' => 'array'];

    public function testigo(): BelongsTo
    {
        return $this->belongsTo(Testigo::class);
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoVotacion::class, 'puesto_id');
    }
}
