<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta de Facebook o Instagram de un candidato, la que se mide.
 */
class RedCuenta extends Model
{
    use BelongsToTenant;

    public const FACEBOOK = 'facebook';

    public const INSTAGRAM = 'instagram';

    public const REDES = [self::FACEBOOK, self::INSTAGRAM];

    // propia = del candidato; rival = otro candidato, solo para comparar
    public const TIPO_PROPIA = 'propia';

    public const TIPO_RIVAL = 'rival';

    protected $table = 'redes_cuentas';

    protected $fillable = [
        'red',
        'tipo',
        'etiqueta',
        'url',
        'usuario',
        'external_id',
        'access_token',
        'activo',
        'ultimo_sync_at',
        'ultimo_error',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'activo' => 'boolean',
        'ultimo_sync_at' => 'datetime',
    ];

    public function metricas(): HasMany
    {
        return $this->hasMany(RedMetrica::class, 'red_cuenta_id');
    }

    public function esRival(): bool
    {
        return $this->tipo === self::TIPO_RIVAL;
    }

    /** Nombre para mostrar: el del rival o el @usuario. */
    public function nombreVisible(): string
    {
        return $this->etiqueta ?: '@'.ltrim($this->usuario, '@');
    }

    public function tieneAccesoPropio(): bool
    {
        return filled($this->access_token);
    }
}
