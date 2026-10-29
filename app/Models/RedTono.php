<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tono de los comentarios de una cuenta del candidato en un día.
 *
 * Solo conteos y temas agregados: el texto de los comentarios y sus autores
 * nunca se guardan.
 */
class RedTono extends Model
{
    use BelongsToTenant;

    protected $table = 'redes_tono';

    protected $fillable = [
        'red_cuenta_id',
        'fecha',
        'a_favor',
        'en_contra',
        'neutro',
        'temas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'temas' => 'array',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(RedCuenta::class, 'red_cuenta_id');
    }

    public function total(): int
    {
        return (int) $this->a_favor + (int) $this->en_contra + (int) $this->neutro;
    }
}
