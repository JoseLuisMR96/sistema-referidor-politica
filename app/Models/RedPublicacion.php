<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Publicación de Instagram de una cuenta del candidato o de un rival.
 *
 * Solo datos públicos de la cuenta (texto, tipo, conteos); nunca comentarios
 * ni quién interactuó.
 */
class RedPublicacion extends Model
{
    use BelongsToTenant;

    protected $table = 'redes_publicaciones';

    /** Temas fijos (clave => texto), para comparar cuentas con las mismas categorías. */
    public const TEMAS = [
        'seguridad' => 'Seguridad',
        'vias' => 'Vías e infraestructura',
        'empleo' => 'Empleo y economía',
        'salud' => 'Salud',
        'educacion' => 'Educación',
        'campo' => 'Campo y agro',
        'ambiente' => 'Medio ambiente',
        'social' => 'Vivienda y programas sociales',
        'transparencia' => 'Corrupción y transparencia',
        'cultura' => 'Deporte y cultura',
        'gira' => 'Recorridos y eventos',
        'contraste' => 'Críticas a rivales o al gobierno',
        'personal' => 'Personal y fechas especiales',
        'otro' => 'Otro',
    ];

    public const TIPOS = [
        'VIDEO' => 'Video o reel',
        'IMAGE' => 'Foto',
        'CAROUSEL_ALBUM' => 'Carrusel',
    ];

    protected $fillable = [
        'red_cuenta_id',
        'media_id',
        'tipo',
        'publicada_at',
        'texto',
        'enlace',
        'me_gusta',
        'comentarios',
        'reproducciones',
        'tema',
    ];

    protected $casts = [
        'publicada_at' => 'datetime',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(RedCuenta::class, 'red_cuenta_id');
    }

    public function interacciones(): int
    {
        return (int) $this->me_gusta + (int) $this->comentarios;
    }
}
