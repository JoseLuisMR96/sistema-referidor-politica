<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Testigo electoral de la campaña: un puesto y unas mesas. Reporta desde
 * /testigo/{token} sin iniciar sesión; el token es su credencial.
 */
class Testigo extends Model
{
    use BelongsToTenant;

    protected $fillable = ['eleccion_id', 'nombre', 'telefono', 'puesto_id', 'mesas', 'token', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    protected $hidden = ['token'];

    protected static function booted(): void
    {
        static::creating(function (Testigo $t) {
            $t->token = $t->token ?: Str::random(40);
        });
    }

    public function eleccion(): BelongsTo
    {
        return $this->belongsTo(Eleccion::class);
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoVotacion::class, 'puesto_id');
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(ReporteMesa::class);
    }

    public function enlace(): string
    {
        return route('testigo.reportar', $this->token);
    }

    /**
     * "1-5, 8" => [1, 2, 3, 4, 5, 8]. Null si el texto no es válido o pasa
     * del máximo de mesas del puesto.
     *
     * @return int[]|null
     */
    public static function mesasDe(string $texto, int $maximo = 300): ?array
    {
        $mesas = [];
        foreach (preg_split('/\s*,\s*/', trim($texto), -1, PREG_SPLIT_NO_EMPTY) as $parte) {
            if (preg_match('/^(\d{1,3})\s*-\s*(\d{1,3})$/', $parte, $m)) {
                [$a, $b] = [(int) $m[1], (int) $m[2]];
                if ($a < 1 || $b < $a || $b > $maximo) {
                    return null;
                }
                $mesas = array_merge($mesas, range($a, $b));
            } elseif (ctype_digit($parte) && (int) $parte >= 1 && (int) $parte <= $maximo) {
                $mesas[] = (int) $parte;
            } else {
                return null;
            }
        }
        $mesas = array_values(array_unique($mesas));
        sort($mesas);

        return $mesas ?: null;
    }

    /** @return int[] */
    public function listaMesas(): array
    {
        return self::mesasDe($this->mesas, 1000) ?? [];
    }
}
