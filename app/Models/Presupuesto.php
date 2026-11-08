<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Tope legal de gastos del candidato (uno por candidato). */
class Presupuesto extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tope_legal', 'tope_fuente'];

    protected $casts = ['tope_legal' => 'decimal:2'];
}
