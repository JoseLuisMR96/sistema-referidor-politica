<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persona del equipo de campaña de un candidato: va a los eventos en su
 * nombre (emisario) o los organiza, y puede recibir dinero para hacerlos.
 */
class IntegranteEquipo extends Model
{
    use BelongsToTenant;

    protected $table = 'equipo_campana';

    public const ROLES = [
        'emisario' => 'Emisario',
        'coordinador' => 'Coordinador',
        'lider' => 'Líder local',
        'logistica' => 'Logística',
        'vocero' => 'Vocero',
        'otro' => 'Otro',
    ];

    protected $fillable = ['nombre', 'rol', 'telefono', 'notas', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function rolTexto(): string
    {
        return self::ROLES[$this->rol] ?? $this->rol;
    }

    /** Eventos de los que es responsable (y recibió el dinero). */
    public function eventosResponsable(): HasMany
    {
        return $this->hasMany(EventoCampana::class, 'responsable_id');
    }

    public function eventos(): BelongsToMany
    {
        return $this->belongsToMany(EventoCampana::class, 'evento_integrante', 'integrante_id', 'evento_id');
    }
}
