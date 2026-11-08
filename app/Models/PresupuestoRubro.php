<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Monto presupuestado para una categoría de gasto (Cuentas Claras). */
class PresupuestoRubro extends Model
{
    use BelongsToTenant;

    protected $table = 'presupuesto_rubros';

    protected $fillable = ['categoria', 'monto'];

    protected $casts = ['monto' => 'decimal:2'];
}
