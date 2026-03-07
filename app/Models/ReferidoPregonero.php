<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferidoPregonero extends Model
{
    protected $table = 'referidos_pregoneros';

    protected $fillable = [
        'referidor_pregonero_id',
        'nombre',
        'cedula',
        'puesto_votacion',
    ];

    public function referidor(): BelongsTo
    {
        return $this->belongsTo(ReferidorPregonero::class, 'referidor_pregonero_id');
    }
}
