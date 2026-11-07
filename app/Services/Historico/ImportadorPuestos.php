<?php

namespace App\Services\Historico;

use Illuminate\Support\Facades\DB;

/**
 * Guarda resultados por puesto de votación (historico_puestos) y el catálogo
 * de puestos (puestos_votacion).
 *
 * Recibe los votos ya sumados por puesto y partido. Reimportar reemplaza por
 * año + cargo + vuelta + municipio, igual que el histórico por municipio.
 */
class ImportadorPuestos
{
    public const BLANCO = 'VOTO EN BLANCO';

    public const NULOS = 'VOTOS NULOS';

    public const NO_MARCADOS = 'VOTOS NO MARCADOS';

    /** Votos que no son de ningún partido ni del blanco. */
    public const NO_VALIDOS = [self::NULOS, self::NO_MARCADOS];

    /** @var array<string, array{0:?int,1:?int}> */
    private array $lugares = [];

    /** @var array<string, int> "munId|zona|codigo" => puesto_id */
    private array $puestos = [];

    public function __construct(private ImportadorHistorico $importador) {}

    /** Partido de una fila "mesa a mesa": 996 blanco, 997 nulos, 998 no marcados. */
    public static function partido(string $nombre, string $codigoCandidato): string
    {
        return match ((int) $codigoCandidato) {
            996 => self::BLANCO,
            997 => self::NULOS,
            998 => self::NO_MARCADOS,
            default => trim(preg_replace('/\s+/u', ' ', $nombre)),
        };
    }

    /** "01COMUNA 1 POPULAR" => "COMUNA 1 POPULAR" */
    public static function comuna(string $texto): ?string
    {
        $t = trim(preg_replace('/^\d+(?=\D)/', '', trim($texto)));

        return $t !== '' ? mb_substr($t, 0, 120) : null;
    }

    /** @return array{0:?int,1:?int} departamento_id, municipio_id */
    public function lugar(string $departamento, string $municipio): array
    {
        return $this->lugares["{$departamento}|{$municipio}"] ??= $this->importador->resolverLugar($departamento, $municipio);
    }

    /** Id del puesto; lo crea si no existe (el nombre del DIVIPOL manda si ya está). */
    public function puesto(int $municipioId, string $zona, string $codigo, string $nombre, ?string $comuna = null): int
    {
        $zona = str_pad(ltrim($zona, '0'), 2, '0', STR_PAD_LEFT);
        $codigo = str_pad(ltrim($codigo, '0'), 2, '0', STR_PAD_LEFT);

        return $this->puestos["{$municipioId}|{$zona}|{$codigo}"] ??= (function () use ($municipioId, $zona, $codigo, $nombre, $comuna) {
            $id = DB::table('puestos_votacion')->where(['municipio_id' => $municipioId, 'zona' => $zona, 'codigo' => $codigo])->value('id');
            if ($id) {
                if ($comuna) {
                    DB::table('puestos_votacion')->where('id', $id)->whereNull('comuna')->update(['comuna' => $comuna]);
                }

                return (int) $id;
            }

            return (int) DB::table('puestos_votacion')->insertGetId([
                'municipio_id' => $municipioId, 'zona' => $zona, 'codigo' => $codigo,
                'nombre' => mb_substr(trim($nombre) ?: "PUESTO {$zona}-{$codigo}", 0, 150),
                'comuna' => $comuna, 'created_at' => now(), 'updated_at' => now(),
            ]);
        })();
    }

    /**
     * @param  iterable<array{departamento:string, municipio:string, zona:string, puesto:string, nombre_puesto:string, comuna:?string, cargo:string, partido:string, votos:int}>  $filas  ya sumadas por puesto y partido
     * @return array{insertadas:int, reemplazadas:int, puestos:int, sin_lugar:array}
     */
    public function guardar(iterable $filas, int $anio, int $vuelta = 1): array
    {
        $resumen = ['insertadas' => 0, 'reemplazadas' => 0, 'puestos' => 0, 'sin_lugar' => []];
        $nuevas = [];
        $claves = [];
        $puestosVistos = [];

        foreach ($filas as $f) {
            // Del archivo exportado llega el municipio ya resuelto (por código DANE)
            $munId = $f['municipio_id'] ?? $this->lugar($f['departamento'], $f['municipio'])[1];
            if (! $munId) {
                $lugar = "{$f['departamento']} / {$f['municipio']}";
                $resumen['sin_lugar'][$lugar] = ($resumen['sin_lugar'][$lugar] ?? 0) + 1;

                continue;
            }

            $puestoId = $this->puesto($munId, $f['zona'], $f['puesto'], $f['nombre_puesto'], $f['comuna'] ?? null);
            $puestosVistos[$puestoId] = true;
            $claves["{$f['cargo']}|{$munId}"] = [$f['cargo'], $munId];
            $clave = "{$f['cargo']}|{$puestoId}|{$f['partido']}";

            if (isset($nuevas[$clave])) {
                $nuevas[$clave]['votos'] += $f['votos'];

                continue;
            }
            $nuevas[$clave] = [
                'anio' => $anio, 'cargo' => $f['cargo'], 'vuelta' => $vuelta, 'municipio_id' => $munId,
                'puesto_id' => $puestoId, 'partido' => mb_substr($f['partido'], 0, 190), 'votos' => (int) $f['votos'],
            ];
        }

        DB::transaction(function () use ($claves, $nuevas, $anio, $vuelta, &$resumen) {
            foreach ($claves as [$cargo, $munId]) {
                $resumen['reemplazadas'] += DB::table('historico_puestos')
                    ->where(['anio' => $anio, 'cargo' => $cargo, 'vuelta' => $vuelta, 'municipio_id' => $munId])
                    ->delete();
            }
            foreach (array_chunk(array_values($nuevas), 1000) as $lote) {
                DB::table('historico_puestos')->insert($lote);
            }
        });

        $resumen['insertadas'] = count($nuevas);
        $resumen['puestos'] = count($puestosVistos);
        arsort($resumen['sin_lugar']);
        HistoricoElectoralStats::invalidarCache();

        return $resumen;
    }

    /**
     * Potencial por puesto desde el DIVIPOL (ancho fijo, mismas columnas que
     * municipios:potencial). Crea los puestos que falten.
     *
     * @return array{puestos:int, sin_lugar:array}
     */
    public function potencialDivipol(string $ruta, int $anio): array
    {
        $resumen = ['puestos' => 0, 'sin_lugar' => []];
        $f = fopen($ruta, 'r');
        while (($l = fgets($f)) !== false) {
            $l = rtrim($l, "\r\n");
            $l = mb_check_encoding($l, 'UTF-8') ? $l : mb_convert_encoding($l, 'UTF-8', 'Windows-1252');
            if (mb_strlen($l) < 114) {
                continue;
            }
            // dep 2 · mun 3 · zona 2 · puesto 2 · depto 12 · municipio 30 · puesto 40 · indicador 1 · mujeres 8 · hombres 8 · mesas 6
            $dep = trim(mb_substr($l, 9, 12));
            if (str_starts_with(mb_strtoupper($dep), 'CONSUL')) {
                continue;
            }
            [, $munId] = $this->lugar($dep, trim(mb_substr($l, 21, 30)));
            if (! $munId) {
                $resumen['sin_lugar'][$dep.' / '.trim(mb_substr($l, 21, 30))] = true;

                continue;
            }

            $mujeres = (int) mb_substr($l, 92, 8);
            $hombres = (int) mb_substr($l, 100, 8);
            $nombre = trim(mb_substr($l, 51, 40));
            $id = $this->puesto($munId, mb_substr($l, 5, 2), mb_substr($l, 7, 2), $nombre);

            DB::table('puestos_votacion')->where('id', $id)->update([
                'nombre' => mb_substr($nombre, 0, 150) ?: DB::raw('nombre'),
                'potencial' => $mujeres + $hombres,
                'potencial_mujeres' => $mujeres,
                'mesas' => (int) mb_substr($l, 108, 6),
                'potencial_anio' => $anio,
                'updated_at' => now(),
            ]);
            $resumen['puestos']++;
        }
        fclose($f);

        return $resumen;
    }
}
