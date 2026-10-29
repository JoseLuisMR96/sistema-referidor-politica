<?php

namespace App\Console\Commands;

use App\Services\Historico\ImportadorHistorico;
use Illuminate\Console\Command;
use PDO;

/**
 * Carga al histórico las bases del "Aplicativo de Estadísticas Electorales"
 * de la Registraduría (Observatorio > Visor histórico > 1997-2015). Al
 * instalarlo deja SQLite en C:\Estadisticas...\db\*.db:
 * - territoriales: Estadisticas.db (gobernación, asamblea, alcaldía, concejo, JAL);
 * - presidencia: Estadisticas1.db (1.ª vuelta) y Estadisticas2.db (2.ª);
 * - Congreso: EstadisticasCon.db (Senado y Cámara).
 *
 * Usa los resúmenes de la base: x_resum_municipio_XX (votos por candidato y
 * municipio), x_resum_comuna_XX (JAL) y elegidos. No lee la tabla candidat,
 * que trae cédulas.
 */
class ImportarEstadisticasRegistraduriaCommand extends Command
{
    protected $signature = 'historico:importar-registraduria
        {ruta : Ruta del archivo .db}
        {--anio= : Año de la elección (por defecto lo lee de la base)}
        {--vuelta= : 1 o 2, solo presidencia (por defecto: 2 si la elección fue en junio)}
        {--depto=* : Solo estos departamentos (nombre, ej. --depto=META)}';

    protected $description = 'Importa resultados electorales desde una base del aplicativo de estadísticas de la Registraduría';

    // Circunscripciones ordinarias (nacional y territorial). Las especiales
    // (indígenas, afro) se dejan fuera para no mezclarlas con la contienda principal.
    private const CIRCUNSCRIPCIONES_ORDINARIAS = [0, 1];

    public function handle(ImportadorHistorico $importador): int
    {
        $ruta = $this->argument('ruta');

        if (! is_file($ruta)) {
            $this->error("No existe el archivo: {$ruta}");

            return self::FAILURE;
        }

        $db = new PDO('sqlite:'.$ruta, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('PRAGMA query_only = ON');

        $fecha = $this->texto((string) $db->query("SELECT valor FROM parametros WHERE descripcion LIKE 'Fecha Eleccion%'")->fetchColumn());
        $anio = (int) ($this->option('anio') ?: (preg_match('/(19|20)\d{2}/', $fecha, $m) ? $m[0] : 0));

        if (! $anio) {
            $this->error('No pude leer el año de la base: pásalo con --anio=2015');

            return self::FAILURE;
        }

        // corp => [nombre del cargo, ámbito: NA nacional, DE departamental, MU municipal, CO comuna]
        $corporaciones = [];
        foreach ($db->query('SELECT corpcoco, corpdesc, corpinde FROM corporac', PDO::FETCH_NUM) as [$corp, $desc, $ambito]) {
            $corporaciones[(int) $corp] = [$this->texto($desc), strtoupper(trim((string) $ambito))];
        }

        $esPresidencia = collect($corporaciones)->contains(fn ($c) => str_contains($c[0], 'PRESIDEN'));
        $vuelta = (int) ($this->option('vuelta') ?: ($esPresidencia && str_contains(mb_strtoupper($fecha), 'JUNIO') ? 2 : 1));

        [$elegidos, $curules] = $this->elegidos($db);
        $soloDeptos = array_map('mb_strtoupper', $this->option('depto'));

        $tablas = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'x_resum_municipio_%' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);

        $cargos = implode(', ', array_column($corporaciones, 0));
        $this->info("Elección {$fecha} ({$cargos})".($esPresidencia ? ", vuelta {$vuelta}" : '').': '.count($tablas).' departamentos.');

        $total = ['insertadas' => 0, 'reemplazadas' => 0, 'omitidas' => 0, 'sin_lugar' => [], 'errores' => []];
        $ignorados = [];

        foreach ($tablas as $tabla) {
            $codigo = substr($tabla, strlen('x_resum_municipio_'));
            $nombreDepto = $this->texto((string) $db->query("SELECT desc_depto FROM {$tabla} LIMIT 1")->fetchColumn());

            // Votos en el exterior: no tienen municipio ni mapa
            if (str_starts_with(mb_strtoupper($nombreDepto), 'CONSULADO')) {
                continue;
            }

            if ($nombreDepto === '' || ($soloDeptos && ! in_array(mb_strtoupper($nombreDepto), $soloDeptos, true))) {
                continue;
            }

            $filas = $this->filas($db, $codigo, $anio, $vuelta, $corporaciones, $elegidos, $curules, $ignorados);
            $total = $importador->guardar($filas, $anio, null, $total);

            $this->line(sprintf('  %-22s %s filas', $nombreDepto, number_format($total['insertadas'], 0, ',', '.')));
        }

        $this->newLine();
        $this->info(number_format($total['insertadas'], 0, ',', '.').' filas importadas, '
            .number_format($total['reemplazadas'], 0, ',', '.').' reemplazadas, '
            .number_format($total['omitidas'], 0, ',', '.').' omitidas (nulos y no marcados).');

        if ($ignorados) {
            $this->warn('Sin importar (no es un cargo del sistema): '.implode(', ', array_keys($ignorados)));
        }

        if ($total['sin_lugar']) {
            $this->warn(count($total['sin_lugar']).' lugares sin reconocer (no se importaron):');
            foreach (array_slice($total['sin_lugar'], 0, 40, true) as $lugar => $n) {
                $this->line("  {$lugar} ({$n})");
            }
        }

        foreach (array_slice($total['errores'], 0, 10) as $e) {
            $this->error($e);
        }

        if ($total['insertadas']) {
            $this->call('historico:precalcular');
        }

        return self::SUCCESS;
    }

    /**
     * Elegidos y curules por partido en cada circunscripción.
     * Clave: corp|depto|municipio|comuna|partido[|candidato], con -1 (o depto 0
     * en lo nacional) donde no aplica, igual que en la tabla elegidos.
     */
    private function elegidos(PDO $db): array
    {
        $elegidos = [];
        $curules = [];

        foreach ($db->query('SELECT corporacion, departamento, municipio, comuna, partido, candidato FROM elegidos', PDO::FETCH_NUM) as [$corp, $dep, $mun, $com, $par, $cand]) {
            $base = "{$corp}|{$dep}|{$mun}|{$com}|{$par}";
            $elegidos["{$base}|{$cand}"] = true;
            $curules[$base] = ($curules[$base] ?? 0) + 1;
        }

        return [$elegidos, $curules];
    }

    private function filas(PDO $db, string $codigo, int $anio, int $vuelta, array $corporaciones, array $elegidos, array $curules, array &$ignorados): \Generator
    {
        $porComuna = array_keys(array_filter($corporaciones, fn ($c) => $c[1] === 'CO'));
        $sinComuna = $porComuna ? 'AND corp NOT IN ('.implode(',', $porComuna).')' : '';

        $tabla = "x_resum_municipio_{$codigo}";

        // Algunos nombres de partido vienen dañados en la base ("DEMOCRÃ?TICO");
        // la fila del voto por la lista (candidato 0) trae el nombre limpio.
        $limpios = [];
        foreach ($db->query("SELECT partido, MAX(desc_candidato) FROM {$tabla} WHERE candidato = 0 GROUP BY partido", PDO::FETCH_NUM) as [$par, $nombre]) {
            $limpios[(int) $par] = rtrim($this->texto($nombre), ' .');
        }
        $consulta = $db->query("SELECT *, -1 AS comuna, NULL AS desc_comuna FROM {$tabla} WHERE 1=1 {$sinComuna}", PDO::FETCH_ASSOC);

        yield from $this->convertir($consulta, $anio, $vuelta, $corporaciones, $elegidos, $curules, $ignorados, $limpios);

        if ($porComuna && $db->query("SELECT 1 FROM sqlite_master WHERE name = 'x_resum_comuna_{$codigo}'")->fetchColumn()) {
            $consulta = $db->query("SELECT * FROM x_resum_comuna_{$codigo}", PDO::FETCH_ASSOC);

            yield from $this->convertir($consulta, $anio, $vuelta, $corporaciones, $elegidos, $curules, $ignorados, $limpios);
        }
    }

    private function convertir(iterable $consulta, int $anio, int $vuelta, array $corporaciones, array $elegidos, array $curules, array &$ignorados, array $limpios = []): \Generator
    {
        foreach ($consulta as $r) {
            if (isset($r['circunscripcion']) && ! in_array((int) $r['circunscripcion'], self::CIRCUNSCRIPCIONES_ORDINARIAS, true)) {
                continue;
            }

            $corp = (int) $r['corp'];
            [$cargo, $ambito] = $corporaciones[$corp] ?? ['', ''];

            if (! $this->cargoValido($cargo)) {
                $ignorados[$cargo ?: "corp {$corp}"] = true;

                continue;
            }

            $dep = $ambito === 'NA' ? 0 : (int) $r['depto'];
            $mun = in_array($ambito, ['MU', 'CO'], true) ? (int) $r['mpio'] : -1;
            $com = $ambito === 'CO' ? (int) $r['comuna'] : -1;
            $base = "{$corp}|{$dep}|{$mun}|{$com}|{$r['partido']}";

            $codCandidato = (int) $r['candidato'];
            $esLista = $codCandidato === 0;
            $especial = $codCandidato >= 996; // blanco, nulos, no marcados
            $partido = $this->texto($r['desc_partido']);
            if (preg_match('/Ã|\?|�/u', $partido) && ($limpios[(int) $r['partido']] ?? '') !== '') {
                $partido = $limpios[(int) $r['partido']];
            }
            $candidato = rtrim($this->texto($r['desc_candidato']), ' .');

            if ($especial) {
                [$partido, $candidato] = [$partido ?: $candidato, null];
            }

            yield [
                'anio' => $anio,
                'cargo' => $cargo,
                'vuelta' => (string) $vuelta,
                'departamento' => $this->texto($r['desc_depto']),
                'municipio' => $this->texto($r['desc_mpio']),
                'comuna' => $this->texto($r['desc_comuna'] ?? ''),
                'partido' => $partido,
                'candidato' => ($esLista || $especial) ? null : $candidato,
                'votos' => (string) $r['votos'],
                'elegido' => ($esLista || $especial || ! $elegidos) ? null : isset($elegidos["{$base}|{$r['candidato']}"]),
                'curules' => $esLista && isset($curules[$base]) ? (string) $curules[$base] : '',
            ];
        }
    }

    private function cargoValido(string $cargo): bool
    {
        foreach (['GOBERN', 'ASAMBLEA', 'ALCALD', 'CONCEJ', 'JAL', 'JUNTA', 'CAMARA', 'SENADO', 'PRESIDEN'] as $clave) {
            if (str_contains($cargo, $clave)) {
                return true;
            }
        }

        return false;
    }

    // Unas bases vienen en UTF-8, otras en Latin-1 y otras con la Ñ codificada dos veces ("NARIÃ‘O")
    private function texto(?string $s): string
    {
        $s = trim((string) $s);

        if (! mb_check_encoding($s, 'UTF-8')) {
            return mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }

        if (str_contains($s, 'Ã') || str_contains($s, 'Â')) {
            $una = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            if (mb_check_encoding($una, 'UTF-8')) {
                return $una;
            }
        }

        return $s;
    }
}
