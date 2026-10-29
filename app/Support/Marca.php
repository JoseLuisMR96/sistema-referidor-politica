<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Colores de marca del candidato.
 *
 * La app usa clases `indigo-*` de Tailwind como color de marca. En
 * tailwind.config.js `indigo` apunta a variables CSS (--marca-50 … --marca-950),
 * cuyos valores por defecto (resources/css/app.css) son el índigo original.
 * Con un candidato elegido, <x-marca> redefine esas variables con la paleta
 * generada aquí y la app completa cambia de color sin tocar las vistas.
 *
 * El color secundario va en --marca-sec (+ --marca-sec-texto, el que se lee
 * encima) y se usa en franjas y cabeceras.
 */
class Marca
{
    /**
     * Posición del color elegido = tono 600 (el de botones y enlaces).
     * Valor > 0: mezcla con blanco; < 0: con negro.
     */
    private const TONOS = [
        50 => 0.95, 100 => 0.88, 200 => 0.75, 300 => 0.58, 400 => 0.35,
        500 => 0.15, 600 => 0.0, 700 => -0.15, 800 => -0.32, 900 => -0.48, 950 => -0.65,
    ];

    public static function esHex(?string $valor): bool
    {
        return is_string($valor) && preg_match('/^#[0-9a-fA-F]{6}$/', $valor) === 1;
    }

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * Paleta Tailwind (50…950) en formato "r g b" para rgb(var(--x) / alpha).
     *
     * @return array<int, string>
     */
    public static function paleta(string $hex): array
    {
        [$r, $g, $b] = self::rgb($hex);
        $paleta = [];

        foreach (self::TONOS as $tono => $mezcla) {
            $destino = $mezcla >= 0 ? 255 : 0;
            $t = abs($mezcla);
            $paleta[$tono] = implode(' ', array_map(
                fn (int $c) => (int) round($c + ($destino - $c) * $t),
                [$r, $g, $b],
            ));
        }

        return $paleta;
    }

    /** Luminancia relativa WCAG. */
    public static function luminancia(string $hex): float
    {
        $canal = function (int $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        [$r, $g, $b] = self::rgb($hex);

        return 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);
    }

    /** Contraste WCAG entre dos colores (1 a 21). */
    public static function contraste(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);

        return round((max($la, $lb) + 0.05) / (min($la, $lb) + 0.05), 2);
    }

    /** Blanco o casi negro: el que mejor se lee sobre $hex. */
    public static function textoSobre(string $hex): string
    {
        return self::contraste($hex, '#ffffff') >= self::contraste($hex, '#111827') ? '#ffffff' : '#111827';
    }

    /**
     * Bloque CSS con las variables del candidato, o '' si no tiene colores
     * (entonces rigen los valores por defecto de app.css).
     */
    public static function css(?Tenant $tenant): string
    {
        if (! $tenant) {
            return '';
        }

        $vars = [];

        if (self::esHex($tenant->color_primario)) {
            foreach (self::paleta($tenant->color_primario) as $tono => $rgb) {
                $vars[] = "--marca-{$tono}: {$rgb};";
            }
        }

        if (self::esHex($tenant->color_secundario)) {
            $vars[] = '--marca-sec: '.implode(' ', self::rgb($tenant->color_secundario)).';';
            $vars[] = '--marca-sec-texto: '.implode(' ', self::rgb(self::textoSobre($tenant->color_secundario))).';';
        }

        foreach (self::tema($tenant) as $nombre => $hex) {
            $vars[] = "--tema-{$nombre}: ".implode(' ', self::rgb($hex)).';';
        }

        return $vars ? ':root{'.implode('', $vars).'}' : '';
    }

    /** ¿Pinta toda la app (fondo, tarjetas, textos) con el color del candidato? */
    public static function temaCompleto(?Tenant $tenant): bool
    {
        return (bool) $tenant?->tema_completo && self::esHex($tenant->color_primario);
    }

    /**
     * Colores del tema completo, derivados del primario. Los textos son
     * blancos o casi negros según qué se lea mejor sobre el fondo; tarjetas y
     * bordes son el fondo mezclado un poco con ese color de texto.
     * El acento (botones, menú activo) es el secundario o, si no hay, el color
     * del texto: un botón del mismo color del fondo no se vería.
     *
     * @return array<string, string> nombre => #hex
     */
    public static function tema(?Tenant $tenant): array
    {
        if (! self::temaCompleto($tenant)) {
            return [];
        }

        $fondo = strtolower($tenant->color_primario);
        $texto = self::textoSobre($fondo);
        $acento = self::esHex($tenant->color_secundario) && self::contraste($tenant->color_secundario, $fondo) >= 3
            ? strtolower($tenant->color_secundario)
            : $texto;

        return [
            'fondo' => $fondo,
            'superficie' => self::mezclar($fondo, $texto, 0.06),
            'superficie-2' => self::mezclar($fondo, $texto, 0.11),
            'borde' => self::mezclar($fondo, $texto, 0.2),
            'texto' => $texto,
            'texto-2' => self::mezclar($texto, $fondo, 0.28),
            'texto-3' => self::mezclar($texto, $fondo, 0.45),
            'acento' => $acento,
            // Acento = texto (sin secundario): encima va el color del fondo
            'acento-texto' => $acento === $texto ? $fondo : self::textoSobre($acento),
        ];
    }

    /** $a llevado hacia $b en proporción $t (0 = $a, 1 = $b). */
    public static function mezclar(string $a, string $b, float $t): string
    {
        $ca = self::rgb($a);
        $cb = self::rgb($b);

        return sprintf('#%02x%02x%02x', ...array_map(
            fn ($x, $y) => (int) round($x + ($y - $x) * $t),
            $ca, $cb,
        ));
    }
}
