<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReferidorPregonero extends Model
{
    protected $table = 'referidores_pregoneros'; 
    protected $fillable = [
        'id_unico',
        'nombre',
        'cedula',
        'celular', 
        'puesto_votacion',
        'monto_pagar',
        'pago_realizado',
        'hora_pago',
        'imagen_pago',
    ];

    protected $casts = [
        'pago_realizado' => 'boolean',
        'hora_pago' => 'datetime',
        'monto_pagar' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id_unico)) {
                $model->id_unico = (string) Str::uuid();
            }
        });
    }

    public function referidos(): HasMany
    {
        return $this->hasMany(ReferidoPregonero::class, 'referidor_pregonero_id');
    }
}
