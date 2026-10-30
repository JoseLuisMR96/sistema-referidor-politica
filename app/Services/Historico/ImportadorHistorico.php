<?php

namespace App\Services\Historico;

use App\Enums\CargoEnum;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa resultados electorales desde CSV o Excel (plantilla en
 * public/plantillas/historico-electoral.csv).
 *
 * Columnas (se aceptan sinónimos, ver COLUMNAS): anio, cargo, departamento,
 * municipio, comuna, partido, candidato, votos, elegido, curules,
 * codigo_dane. Año y cargo pueden venir del formulario si el archivo no los trae.
 *
 * Volver a importar reemplaza: se borran antes las filas del mismo
 * año + cargo + departamento + municipio que trae el archivo.
 */
class ImportadorHistorico
{
    private const COLUMNAS = [
        'anio' => ['anio', 'ano', 'year', 'eleccion'],
        'cargo' => ['cargo', 'corporacion', 'nombre corporacion'],
        'vuelta' => ['vuelta'],
        'departamento' => ['departamento', 'depto', 'dpto', 'nombre departamento'],
        'municipio' => ['municipio', 'mpio', 'nombre municipio'],
        'comuna' => ['nombre comuna', 'comuna', 'localidad', 'comuna localidad'],
        'partido' => ['partido', 'agrupacion', 'agrupacion politica', 'partido politico', 'nombre partido', 'lista'],
        'candidato' => ['candidato', 'nombre candidato', 'nombre'],
        'codigo_candidato' => ['codigo candidato'],
        'votos' => ['votos', 'total votos', 'votacion'],
        'elegido' => ['elegido', 'electo', 'ganador'],
        'curules' => ['curules', 'escanos', 'curul'],
        // Ojo: "Código Municipio" de la Registraduría NO es DANE; no va aquí
        'codigo_dane' => ['codigo dane', 'cod mpio dane', 'divipola'],
        'circunscripcion' => ['nombre circunscripcion', 'circunscripcion'],
    ];

    // Lo que no es de un candidato ni lista: no se guarda
    private const NO_CANDIDATOS = ['VOTOS NULOS', 'VOTO NULO', 'NULOS', 'VOTOS NO MARCADOS', 'NO MARCADOS', 'TARJETAS NO MARCADAS'];

    // Nombres cortos que usa la Registraduría => código DANE del municipio
    private const ALIAS_DANE = [
        'BUGA' => ['76111'],
        'TUMACO' => ['52835'],
        'BOLIVAR' => ['05101'],      // Ciudad Bolívar (Antioquia)
        'SANPEDRO' => ['05664'],     // San Pedro de los Milagros (Antioquia)
        'UBATE' => ['25843'],
        'VILLADELEIVA' => ['15407'],
        'TOLU' => ['70820'],
        'PAEZBELALCAZAR' => ['19517'],
    ];

    private array $departamentos = [];

    private array $municipios = [];

    private array $codigos = [];

    /**
     * @return array{insertadas:int, reemplazadas:int, omitidas:int, sin_lugar:array, errores:array}
     */
    public function importar(string $ruta, ?int $anioDefecto = null, ?string $cargoDefecto = null, ?string $nombreOriginal = null, ?int $vueltaDefecto = null): array
    {
        $this->cargarCatalogo();

        $filas = $this->leer($ruta, $nombreOriginal ?? $ruta);
        $encabezado = $filas->current() ?? [];
        $filas->next();
        $idx = $this->mapearColumnas($encabezado);

        $resumen = ['insertadas' => 0, 'reemplazadas' => 0, 'omitidas' => 0, 'sin_lugar' => [], 'errores' => []];

        foreach (['departamento', 'partido', 'votos'] as $obligatoria) {
            if (! isset($idx[$obligatoria])) {
                $resumen['errores'][] = "Falta la columna \"{$obligatoria}\".";
            }
        }
        if (! isset($idx['anio']) && ! $anioDefecto) {
            $resumen['errores'][] = 'El archivo no trae la columna "anio": elige el año en el formulario.';
        }
        if (! isset($idx['cargo']) && ! $cargoDefecto) {
            $resumen['errores'][] = 'El archivo no trae la columna "cargo": elige el cargo en el formulario.';
        }
        if ($resumen['errores']) {
            return $resumen;
        }

        $asociativas = (function () use ($filas, $idx) {
            for (; $filas->valid(); $filas->next()) {
                $fila = $filas->current();
                $out = [];
                foreach ($idx as $col => $i) {
                    $out[$col] = $fila[$i] ?? '';
                }
                yield $out;
            }
        })();

        return $this->guardar($asociativas, $anioDefecto, $cargoDefecto, $resumen, $vueltaDefecto);
    }

    /**
     * Guarda filas ya leídas (arreglos con las claves de COLUMNAS). La usan
     * el importador de archivos y el de la base de la Registraduría.
     */
    public function guardar(iterable $filas, ?int $anioDefecto = null, ?string $cargoDefecto = null, ?array $resumen = null, ?int $vueltaDefecto = null): array
    {
        $this->cargarCatalogo();
        $resumen ??= ['insertadas' => 0, 'reemplazadas' => 0, 'omitidas' => 0, 'sin_lugar' => [], 'errores' => []];
        $nuevas = [];
        $claves = [];
        $ahora = now();

        // Los archivos "mesa a mesa" repiten los mismos textos millones de
        // veces: se normalizan una sola vez y se guarda el resultado.
        $memoNombres = [];
        $memoLugares = [];
        $memoCargos = [];

        foreach ($filas as $n => $fila) {
            $partido = trim((string) ($fila['partido'] ?? ''));
            $candidato = trim((string) ($fila['candidato'] ?? ''));
            $codCandidato = trim((string) ($fila['codigo_candidato'] ?? ''));

            // partido y candidato limpios, o false si se omite (nulos, no marcados)
            $memo = $memoNombres["{$partido}|{$candidato}|{$codCandidato}"] ??= ($this->nombres($partido, $candidato, $codCandidato) ?? false);
            if ($memo === false) {
                $resumen['omitidas']++;

                continue;
            }
            [$partido, $candidato] = $memo;

            $departamento = trim((string) ($fila['departamento'] ?? ''));
            $circ = (string) ($fila['circunscripcion'] ?? '');
            if (($circ !== '' && ! ctype_digit($circ) && preg_match('/INDIGEN|AFRO|ESPECIAL|INTERNACIONAL|EXTERIOR|CITREP|PAZ|RAIZAL/i', $circ))
                || stripos($departamento, 'CONSULADO') === 0) {
                $resumen['omitidas']++;

                continue;
            }

            $anio = (int) (($fila['anio'] ?? '') ?: $anioDefecto);
            $textoCargo = trim((string) ($fila['cargo'] ?? ''));
            $cargo = ($memoCargos[$textoCargo] ??= ($this->cargo($textoCargo) ?? '')) ?: $cargoDefecto;

            if ($anio < 1950 || $anio > 2100 || ! CargoEnum::tryFrom((string) $cargo)) {
                $linea = is_int($n) ? $n + 2 : $n;
                $resumen['errores'][] = "Fila {$linea}: año o cargo inválido.";

                continue;
            }

            $municipio = trim((string) ($fila['municipio'] ?? ''));
            $codigoDane = trim((string) ($fila['codigo_dane'] ?? ''));
            [$depId, $munId] = $memoLugares["{$departamento}|{$municipio}|{$codigoDane}"] ??= $this->lugar($departamento, $municipio, $codigoDane);

            if (! $depId || ($municipio !== '' && ! $munId)) {
                $lugar = trim("{$departamento} / {$municipio}", ' /');
                $resumen['sin_lugar'][$lugar] = ($resumen['sin_lugar'][$lugar] ?? 0) + 1;

                continue;
            }

            $vuelta = max(1, (int) (($fila['vuelta'] ?? '') ?: $vueltaDefecto ?: 1));
            $claves["{$anio}|{$cargo}|{$vuelta}|{$depId}|{$munId}"] ??= [$anio, $cargo, $vuelta, $depId, $munId];

            $comuna = null;
            if ($cargo === 'jal' && ($fila['comuna'] ?? '') !== '') {
                // "01COMUNA 1 POPULAR" => "COMUNA 1 POPULAR"
                $comuna = preg_replace('/^\d+(?=\D)/', '', trim((string) $fila['comuna']));
            }

            $votosTexto = trim((string) ($fila['votos'] ?? ''));
            $votos = ctype_digit($votosTexto) ? (int) $votosTexto : (int) preg_replace('/\D/', '', $votosTexto);
            $clave = "{$anio}|{$cargo}|{$vuelta}|{$depId}|{$munId}|{$comuna}|{$partido}|{$candidato}";

            // Los archivos "mesa a mesa" traen una fila por mesa o puesto: se suman por lugar
            if (isset($nuevas[$clave])) {
                $nuevas[$clave]['votos'] += $votos;

                continue;
            }

            $elegido = $fila['elegido'] ?? null;
            $curules = trim((string) ($fila['curules'] ?? ''));

            $nuevas[$clave] = [
                'anio' => $anio,
                'cargo' => $cargo,
                'vuelta' => $vuelta,
                'departamento_id' => $depId,
                'municipio_id' => $munId,
                'comuna' => $comuna,
                'partido' => mb_substr($partido, 0, 190),
                'candidato' => $candidato !== null ? mb_substr($candidato, 0, 190) : null,
                'votos' => $votos,
                'elegido' => is_bool($elegido) ? $elegido : $this->siNo(trim((string) $elegido)),
                'curules' => $curules !== '' ? (int) $curules : null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::transaction(function () use ($claves, $nuevas, &$resumen) {
            foreach ($claves as [$anio, $cargo, $vuelta, $depId, $munId]) {
                $resumen['reemplazadas'] += DB::table('historico_electoral')
                    ->where(compact('anio', 'cargo', 'vuelta'))
                    ->where('departamento_id', $depId)
                    ->when($munId, fn ($q) => $q->where('municipio_id', $munId), fn ($q) => $q->whereNull('municipio_id'))
                    ->delete();
            }

            foreach (array_chunk(array_values($nuevas), 500) as $lote) {
                DB::table('historico_electoral')->insert($lote);
            }
        });

        $resumen['insertadas'] += count($nuevas);
        arsort($resumen['sin_lugar']);

        // Cambiaron los datos: la caché del módulo se renueva
        HistoricoElectoralStats::invalidarCache();

        return $resumen;
    }

    // Nulos y no marcados, también con basura al final ("VOTOS NULOS N")
    private function esNoCandidato(string $normalizado): bool
    {
        foreach (self::NO_CANDIDATOS as $nc) {
            if ($normalizado === $nc || str_starts_with($normalizado, $nc.' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Partido y candidato limpios, o null si la fila no es de un candidato ni
     * lista (nulos, no marcados).
     * - Voto por la lista: código de candidato 0 / 00000 => candidato null.
     * - Votos especiales con partido "N/A": el nombre viene en el candidato.
     */
    private function nombres(string $partido, string $candidato, string $codCandidato): ?array
    {
        // 2022: "VOTOS NULOS<tabuladores>N": se colapsan los espacios raros
        $partido = trim(preg_replace('/\s+/u', ' ', $partido));
        $candidato = trim(preg_replace('/\s+/u', ' ', $candidato));

        $normPartido = HistoricoElectoralStats::normalizar($partido);
        $normCandidato = HistoricoElectoralStats::normalizar($candidato);

        if (in_array($normPartido, ['N A', 'NA', ''], true) && $candidato !== '') {
            [$partido, $normPartido, $candidato, $normCandidato] = [$candidato, $normCandidato, '', ''];
        }

        if ($normPartido === '' || $this->esNoCandidato($normPartido) || $this->esNoCandidato($normCandidato)) {
            return null;
        }

        if (str_starts_with($normPartido, 'VOTOS EN BLANCO') || in_array($normPartido, ['BLANCOS', 'VOTO BLANCO', 'VOTO EN BLANCO'], true)) {
            return ['VOTO EN BLANCO', null];
        }

        $esLista = $codCandidato !== '' && ctype_digit($codCandidato) && (int) $codCandidato === 0;
        if ($esLista || in_array($normCandidato, ['N A', 'NA', 'SIN CANDIDATO', ''], true)) {
            return [$partido, null];
        }

        return [$partido, $candidato];
    }

    /**
     * Filas del archivo, una por una (el "mesa a mesa" nacional pesa ~3 GB:
     * no cabe en memoria).
     */
    private function leer(string $ruta, string $nombre): \Generator
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $reader = IOFactory::createReaderForFile($ruta);
            $reader->setReadDataOnly(true);

            yield from $reader->load($ruta)->getActiveSheet()->toArray(null, true, false, false);

            return;
        }

        $f = fopen($ruta, 'r');
        $muestra = (string) fread($f, 65536);

        // UTF-16 (los "mesa a mesa" de Congreso 2022 vienen así): se convierte al
        // vuelo con un filtro de stream, sin cargar el archivo en memoria
        $utf16 = match (substr($muestra, 0, 2)) {
            "\xFF\xFE" => 'UTF-16LE',
            "\xFE\xFF" => 'UTF-16BE',
            default => null,
        };

        if ($utf16) {
            $cuerpo = substr($muestra, 2);
            $muestra = mb_convert_encoding(substr($cuerpo, 0, strlen($cuerpo) - strlen($cuerpo) % 2), 'UTF-8', $utf16);
        }

        $muestra = preg_replace('/^\xEF\xBB\xBF/', '', $muestra);
        $primera = strtok($muestra, "\r\n") ?: '';
        $separador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        // Se revisa hasta el último salto de línea para no cortar un carácter a la mitad
        $corte = strrpos($muestra, "\n");
        $latin = ! $utf16 && ! mb_check_encoding($corte ? substr($muestra, 0, $corte) : $muestra, 'UTF-8');

        rewind($f);
        if ($utf16) {
            fread($f, 2); // marca de orden de bytes
            stream_filter_append($f, "convert.iconv.{$utf16}/UTF-8", STREAM_FILTER_READ);
        } elseif (fread($f, 3) !== "\xEF\xBB\xBF") {
            rewind($f);
        }

        while (($fila = fgetcsv($f, 0, $separador, '"', '')) !== false) {
            if ($fila === [null]) {
                continue; // línea vacía
            }
            // Hay archivos con codificación mezclada (UTF-8 con las Ñ en Latin-1): se revisa cada fila
            if ($latin || ! mb_check_encoding(implode('', $fila), 'UTF-8')) {
                $fila = array_map(fn ($v) => mb_check_encoding((string) $v, 'UTF-8') ? $v : mb_convert_encoding((string) $v, 'UTF-8', 'Windows-1252'), $fila);
            }

            yield $fila;
        }

        fclose($f);
    }

    private function mapearColumnas(array $encabezado): array
    {
        $idx = [];

        foreach ($encabezado as $i => $titulo) {
            $t = strtolower(HistoricoElectoralStats::normalizar((string) $titulo));

            foreach (self::COLUMNAS as $col => $sinonimos) {
                if (! isset($idx[$col]) && in_array($t, $sinonimos, true)) {
                    $idx[$col] = $i;
                }
            }
        }

        return $idx;
    }

    private function cargo(string $texto): ?string
    {
        $t = HistoricoElectoralStats::normalizar($texto);

        if ($t === '') {
            return null;
        }
        if ($c = CargoEnum::tryFrom(strtolower($t))) {
            return $c->value;
        }

        return match (true) {
            str_contains($t, 'GOBERN') => CargoEnum::GOBERNACION->value,
            str_contains($t, 'ASAMBLEA') || str_contains($t, 'DIPUTADO') => CargoEnum::ASAMBLEA->value,
            str_contains($t, 'ALCALD') => CargoEnum::ALCALDIA->value,
            str_contains($t, 'CONCEJ') => CargoEnum::CONCEJO->value,
            str_contains($t, 'JUNTA') || str_contains($t, 'JAL') || str_contains($t, 'EDIL') => CargoEnum::JAL->value,
            str_contains($t, 'CAMARA') => CargoEnum::CAMARA->value,
            str_contains($t, 'SENADO') => CargoEnum::SENADO->value,
            str_contains($t, 'PRESIDEN') => CargoEnum::PRESIDENCIA->value,
            default => null,
        };
    }

    private function siNo(string $v): ?bool
    {
        $v = HistoricoElectoralStats::normalizar($v);

        return match (true) {
            $v === '' => null,
            in_array($v, ['SI', 'X', '1', 'TRUE', 'ELEGIDO', 'ELECTO', 'GANADOR'], true) => true,
            default => false,
        };
    }

    /** @return array{0:?int,1:?int} departamento_id y municipio_id */
    private function lugar(string $departamento, string $municipio, string $codigo): array
    {
        if ($codigo !== '' && isset($this->codigos[str_pad(preg_replace('/\D/', '', $codigo), 5, '0', STR_PAD_LEFT)])) {
            return $this->codigos[str_pad(preg_replace('/\D/', '', $codigo), 5, '0', STR_PAD_LEFT)];
        }

        $depId = $this->departamento($departamento);

        if (! $depId || $municipio === '') {
            return [$depId, null];
        }

        $clave = $this->clave($municipio);

        return [$depId, $this->municipios[$depId][$clave]
            ?? $this->municipios[$depId][$this->sinPrefijo($clave)]
            ?? $this->porAlias($depId, $clave)
            ?? $this->porPrefijo($depId, $clave)];
    }

    private function porAlias(int $depId, string $clave): ?int
    {
        foreach (self::ALIAS_DANE[$clave] ?? [] as $codigo) {
            [$dep, $mun] = $this->codigos[$codigo] ?? [null, null];
            if ((int) $dep === $depId) {
                return $mun;
            }
        }

        return null;
    }

    /**
     * "SAN MARTIN DE LOS LLANOS" = "SAN MARTIN": un nombre empieza por el
     * otro. Solo si hay una única coincidencia en el departamento.
     */
    private function porPrefijo(int $depId, string $clave): ?int
    {
        $ids = [];

        foreach ($this->municipios[$depId] ?? [] as $otra => $id) {
            $corta = strlen($otra) < strlen($clave) ? $otra : $clave;
            $larga = $corta === $otra ? $clave : $otra;

            if (strlen($corta) >= 5 && str_starts_with($larga, $corta)) {
                $ids[$id] = true;
            }
        }

        return count($ids) === 1 ? array_key_first($ids) : null;
    }

    private function departamento(string $nombre): ?int
    {
        $n = HistoricoElectoralStats::normalizar($nombre);

        if (isset($this->departamentos[$n])) {
            return $this->departamentos[$n];
        }

        $alias = match (true) {
            str_starts_with($n, 'BOGOTA') => 'BOGOTA',
            str_contains($n, 'SAN ANDRES') => 'SAN ANDRES',
            str_starts_with($n, 'VALLE') => 'VALLE DEL CAUCA',
            str_contains($n, 'GUAJIRA') => 'LA GUAJIRA',
            str_starts_with($n, 'NORTE DE SAN') => 'NORTE DE SANTANDER',
            default => null,
        };

        return $alias ? ($this->departamentos[$alias] ?? null) : null;
    }

    private function cargarCatalogo(): void
    {
        if ($this->departamentos) {
            return;
        }

        foreach (DB::table('departamentos')->get(['id', 'nombre']) as $d) {
            $this->departamentos[HistoricoElectoralStats::normalizar($d->nombre)] = $d->id;
            $this->departamentos[HistoricoElectoralStats::normalizar($this->sinEnie($d->nombre))] ??= $d->id;
        }

        foreach (DB::table('municipios')->get(['id', 'departamento_id', 'nombre', 'codigo_dane']) as $m) {
            $this->municipios[$m->departamento_id][$this->clave($m->nombre)] = $m->id;
            $this->municipios[$m->departamento_id][$this->sinPrefijo($this->clave($m->nombre))] ??= $m->id;
            $this->municipios[$m->departamento_id][$this->clave($this->sinEnie($m->nombre))] ??= $m->id;
            if ($m->codigo_dane) {
                $this->codigos[$m->codigo_dane] = [$m->departamento_id, $m->id];
            }
        }

        // Nombres oficiales de los mapas ("CARTAGENA DE INDIAS", "SAN VICENTE FERRER")
        $ruta = database_path('data/municipios_dane.csv');
        if (is_file($ruta) && $f = fopen($ruta, 'r')) {
            fgetcsv($f, 0, ',', '"', '');
            while (($fila = fgetcsv($f, 0, ',', '"', '')) !== false) {
                [, $depId, $codigo, $nombre] = $fila;
                if ($par = $this->codigos[$codigo] ?? null) {
                    $this->municipios[$depId][$this->clave($nombre)] ??= $par[1];
                    $this->municipios[$depId][$this->sinPrefijo($this->clave($nombre))] ??= $par[1];
                    $this->municipios[$depId][$this->clave($this->sinEnie($nombre))] ??= $par[1];
                }
            }
            fclose($f);
        }
    }

    // Comparación tolerante: sin tildes, espacios ni puntos ("BOGOTA. D.C." = "BOGOTADC")
    private function clave(string $nombre): string
    {
        $nombre = preg_replace('/\s*\(.*?\)\s*/', ' ', $nombre);

        return preg_replace('/(?<=[A-Z]{4})DC$/', '', str_replace(' ', '', HistoricoElectoralStats::normalizar($nombre)));
    }

    // El archivo 2019 de la Registraduría trae los nombres sin la Ñ ("PUERTO CARREO", "PEOL")
    private function sinEnie(string $nombre): string
    {
        return str_replace(['Ñ', 'ñ'], '', $nombre);
    }

    private function sinPrefijo(string $clave): string
    {
        return preg_replace('/^(EL|LA|LOS|LAS)(?=[A-Z]{3})/', '', $clave);
    }
}
