<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto o soporte de un evento de campaña. El archivo va en el disco privado
 * (local) y se sirve solo por la ruta eventos.evidencia, con permiso y dentro
 * del candidato.
 */
class EvidenciaEvento extends Model
{
    use BelongsToTenant;

    protected $table = 'evidencias_evento';

    public const DISCO = 'local';

    protected $fillable = ['evento_id', 'tipo', 'ruta', 'nombre', 'mime', 'tamano', 'descripcion', 'subido_por'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCampana::class, 'evento_id');
    }

    public function esFoto(): bool
    {
        return $this->tipo === 'foto';
    }

    public function url(): string
    {
        return route('eventos.evidencia', $this);
    }
}
