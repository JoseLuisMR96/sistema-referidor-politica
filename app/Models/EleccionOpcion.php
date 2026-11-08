<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Candidato o lista que se cuenta en el E-14 (va con su elección, sin tenant propio). */
class EleccionOpcion extends Model
{
    protected $table = 'eleccion_opciones';

    public $timestamps = false;

    protected $fillable = ['nombre', 'partido', 'propia', 'orden'];

    protected $casts = ['propia' => 'boolean'];

    public function eleccion(): BelongsTo
    {
        return $this->belongsTo(Eleccion::class);
    }
}
