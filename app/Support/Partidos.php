<?php

namespace App\Support;

use App\Services\Historico\HistoricoElectoralStats;

/**
 * Identidad de un partido a lo largo de los años.
 *
 * La Registraduría escribe distinto el mismo partido según la elección
 * ("PARTIDO LIBERAL COLOMBIANO", "LIBERAL COLOMBIANO"; "PARTIDO DE LA U",
 * "PARTIDO SOCIAL DE UNIDAD NACIONAL PARTIDO DE LA U", "PARTIDO DE LA UNIÓN
 * POR LA GENTE - PARTIDO DE LA U"). clave() los lleva a una sola clave para
 * poder seguirlos entre elecciones.
 *
 * Las coaliciones ("PARTIDO LIBERAL - PARTIDO ALIANZA VERDE", "COALICIÓN …",
 * "FE Y FIRMEZA") quedan como una entidad propia: no se reparten entre sus
 * partidos.
 */
class Partidos
{
    /** Fragmento del nombre normalizado => clave del partido. El orden importa. */
    private const CONOCIDOS = [
        'VOTO EN BLANCO' => 'VOTO EN BLANCO',
        'DE LA U' => 'PARTIDO DE LA U',
        'SOCIAL DE UNIDAD NACIONAL' => 'PARTIDO DE LA U',
        'SOCIAL DE LA UNIDAD NACIONAL' => 'PARTIDO DE LA U',
        'NUEVO LIBERALISMO' => 'NUEVO LIBERALISMO',
        'LIBERAL' => 'PARTIDO LIBERAL',
        'CONSERVADOR' => 'PARTIDO CONSERVADOR',
        'CENTRO DEMOCRATICO' => 'CENTRO DEMOCRÁTICO',
        'CAMBIO RADICAL' => 'CAMBIO RADICAL',
        'ALIANZA VERDE' => 'ALIANZA VERDE',
        'PACTO HISTORICO' => 'PACTO HISTÓRICO',
        'COLOMBIA HUMANA' => 'COLOMBIA HUMANA',
        'POLO DEMOCRATICO' => 'POLO DEMOCRÁTICO',
        'ALIANZA SOCIAL INDEPENDIENTE' => 'ALIANZA SOCIAL INDEPENDIENTE (ASI)',
        'AUTORIDADES INDIGENAS' => 'AICO',
        'ALTERNATIVO INDIGENA Y SOCIAL' => 'MAIS',
        'MIRA' => 'MIRA',
        'COLOMBIA JUSTA LIBRES' => 'COLOMBIA JUSTA LIBRES',
        'SALVACION NACIONAL' => 'SALVACIÓN NACIONAL',
        'LIGA DE GOBERNANTES ANTICORRUPCION' => 'LIGA DE GOBERNANTES ANTICORRUPCIÓN',
        'DEFENSORES DE LA PATRIA' => 'DEFENSORES DE LA PATRIA',
        'DIGNIDAD' => 'DIGNIDAD & COMPROMISO',
        'COMUNES' => 'COMUNES',
        'FARC' => 'COMUNES',
        'UNION PATRIOTICA' => 'UNIÓN PATRIÓTICA',
        'OPCION CIUDADANA' => 'OPCIÓN CIUDADANA',
        'GENTE EN MOVIMIENTO' => 'GENTE EN MOVIMIENTO',
        'CREEMOS' => 'CREEMOS',
        'EN MARCHA' => 'EN MARCHA',
        'FUERZA CIUDADANA' => 'FUERZA CIUDADANA',
        'NUEVA FUERZA DEMOCRATICA' => 'NUEVA FUERZA DEMOCRÁTICA',
    ];

    /** Siglas que solo sirven para detectar coaliciones ("PARTIDO DE LA U - AICO"). */
    private const SIGLAS = ['AICO' => 'AICO', 'MAIS' => 'MAIS', 'ASI' => 'ALIANZA SOCIAL INDEPENDIENTE (ASI)'];

    /** Claves de los partidos conocidos que aparecen en el nombre. */
    private static function encontrados(string $normalizado, bool $conSiglas = false): array
    {
        $claves = [];
        foreach ($conSiglas ? self::CONOCIDOS + self::SIGLAS : self::CONOCIDOS as $fragmento => $clave) {
            if (preg_match('/\b'.preg_quote($fragmento, '/').'\b/', $normalizado)) {
                $claves[$clave] ??= $fragmento;
            }
        }

        return $claves;
    }

    private static function sinPrefijo(string $normalizado): string
    {
        return trim(preg_replace('/^(PARTIDO|MOVIMIENTO)( POLITICO)?\s+/', '', $normalizado)) ?: $normalizado;
    }

    /** ¿Es una coalición o lista con nombre propio que no es un partido solo? */
    public static function esCoalicion(string $nombre): bool
    {
        $n = HistoricoElectoralStats::normalizar($nombre);

        if (str_starts_with($n, 'COALICION') || count(self::encontrados($n, true)) > 1) {
            return true;
        }

        // El nombre oficial reciente de La U lleva guion ("… POR LA GENTE - PARTIDO DE LA U")
        return str_contains((string) $nombre, '-') && ! str_ends_with($n, 'PARTIDO DE LA U');
    }

    /** Clave estable del partido (o el nombre limpio, si es coalición o desconocido). */
    public static function clave(?string $nombre): string
    {
        $n = HistoricoElectoralStats::normalizar(trim((string) $nombre));
        $limpio = self::sinPrefijo($n);
        $encontrados = self::encontrados($n);

        if (self::esCoalicion((string) $nombre) || count($encontrados) !== 1) {
            return $limpio;
        }

        $clave = array_key_first($encontrados);
        $fragmento = $encontrados[$clave];

        // El nombre debe empezar por el partido ("ALIANZA SOCIAL INDEPENDIENTE ASI");
        // "DE LA U" también vale al final ("… UNIDAD NACIONAL PARTIDO DE LA U").
        if (str_starts_with($limpio, $fragmento) || ($fragmento === 'DE LA U' && str_ends_with($n, 'DE LA U'))) {
            return $clave;
        }

        return $limpio;
    }
}
