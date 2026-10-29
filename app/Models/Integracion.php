<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Credenciales globales de un proveedor externo (hoy solo Meta).
 *
 * Las credenciales se guardan cifradas con APP_KEY y nunca se devuelven
 * completas a la interfaz (ver Livewire\Admin\Redes\Index).
 */
class Integracion extends Model
{
    public const META = 'meta';

    // API de Claude para clasificar el tono de los comentarios
    public const ANTHROPIC = 'anthropic';

    protected $table = 'integraciones';

    protected $fillable = [
        'proveedor',
        'credenciales',
        'activo',
    ];

    protected $hidden = [
        'credenciales',
    ];

    protected $casts = [
        'credenciales' => 'encrypted:array',
        'activo' => 'boolean',
    ];

    public static function meta(): self
    {
        return static::firstOrNew(['proveedor' => self::META]);
    }

    public function credencial(string $clave, mixed $default = null): mixed
    {
        return ($this->credenciales ?? [])[$clave] ?? $default;
    }
}
