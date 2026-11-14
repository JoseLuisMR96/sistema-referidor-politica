<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Evento de campaña: dónde y cuándo estuvo el candidato o su equipo, quién
 * respondió, cuánto dinero se le asignó y con qué evidencias.
 */
class EventoCampana extends Model
{
    use BelongsToTenant;

    protected $table = 'eventos_campana';

    public const TIPOS = [
        'reunion' => 'Reunión',
        'visita' => 'Visita',
        'foro' => 'Foro o debate',
        'caravana' => 'Caravana',
        'puerta_a_puerta' => 'Puerta a puerta',
        'evento_publico' => 'Evento público',
        'medios' => 'Medios (radio, TV)',
        'capacitacion' => 'Capacitación',
        'otro' => 'Otro',
    ];

    public const ESTADOS = [
        'planeado' => ['Planeado', 'bg-sky-100 text-sky-800'],
        'realizado' => ['Realizado', 'bg-emerald-100 text-emerald-800'],
        'cancelado' => ['Cancelado', 'bg-gray-100 text-gray-600'],
    ];

    protected $fillable = [
        'titulo', 'tipo', 'estado', 'fecha', 'departamento_id', 'municipio_id', 'lugar', 'direccion',
        'latitud', 'longitud', 'asistio_candidato', 'responsable_id', 'asistentes',
        'monto_asignado', 'monto_gastado', 'descripcion', 'resultado', 'creado_por',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'asistio_candidato' => 'boolean',
        'monto_asignado' => 'decimal:2',
        'monto_gastado' => 'decimal:2',
        'latitud' => 'float',
        'longitud' => 'float',
    ];

    public function tipoTexto(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(IntegranteEquipo::class, 'responsable_id');
    }

    /**
     * Nombre de quien responde por el evento (y su dinero): el responsable del
     * equipo, o el candidato si fue en persona y no se eligió a nadie.
     */
    public function responsableTexto(): ?string
    {
        if ($this->responsable) {
            return $this->responsable->nombre;
        }

        return $this->asistio_candidato ? 'El candidato' : null;
    }

    /** Otros integrantes del equipo que fueron. */
    public function integrantes(): BelongsToMany
    {
        return $this->belongsToMany(IntegranteEquipo::class, 'evento_integrante', 'evento_id', 'integrante_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaEvento::class, 'evento_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Quién estuvo: "Candidato", "Emisario: Ana Pérez" o "Equipo". */
    public function quienFue(): string
    {
        if ($this->asistio_candidato) {
            return 'Candidato';
        }

        return $this->responsable ? "{$this->responsable->rolTexto()}: {$this->responsable->nombre}" : 'Equipo';
    }

    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }
}
