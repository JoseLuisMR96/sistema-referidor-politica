<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto diaria de una cuenta: seguidores, publicaciones e interacciones.
 */
class RedMetrica extends Model
{
    use BelongsToTenant;

    protected $table = 'redes_metricas';

    protected $fillable = [
        'red_cuenta_id',
        'fecha',
        'seguidores',
        'publicaciones',
        'interacciones_7d',
        'datos',
    ];

    protected $casts = [
        'fecha' => 'date',
        'datos' => 'array',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(RedCuenta::class, 'red_cuenta_id');
    }
}
