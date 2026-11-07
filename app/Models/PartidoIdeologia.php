<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ideología de un partido (por Partidos::clave()). Global: igual para todos
 * los candidatos. Lo que no está aquí cuenta como "sin clasificar".
 */
class PartidoIdeologia extends Model
{
    protected $table = 'partidos_ideologia';

    protected $fillable = ['clave', 'ideologia', 'nota'];

    /** Valor => [etiqueta, posición de -2 (izquierda) a 2 (derecha), color] */
    public const IDEOLOGIAS = [
        'izquierda' => ['Izquierda', -2, '#b91c1c'],
        'centro_izquierda' => ['Centro-izquierda', -1, '#f87171'],
        'centro' => ['Centro', 0, '#a3a3a3'],
        'centro_derecha' => ['Centro-derecha', 1, '#60a5fa'],
        'derecha' => ['Derecha', 2, '#1d4ed8'],
    ];

    /** Los tres bloques que se comparan entre años. */
    public const BLOQUES = [
        'izquierda' => ['Izquierda', '#dc2626'],
        'centro' => ['Centro', '#737373'],
        'derecha' => ['Derecha', '#2563eb'],
    ];

    public const SIN_CLASIFICAR_COLOR = '#d6d3d1';

    public static function bloque(?string $ideologia): ?string
    {
        return match ($ideologia) {
            'izquierda', 'centro_izquierda' => 'izquierda',
            'centro' => 'centro',
            'centro_derecha', 'derecha' => 'derecha',
            default => null,
        };
    }

    /** clave => ideologia */
    public static function mapa(): array
    {
        return static::query()->pluck('ideologia', 'clave')->all();
    }
}
