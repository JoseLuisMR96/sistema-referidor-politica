<?php

namespace App\Enums;

enum ButtonIdEnum: string
{
    case PALOM = 'palom';
    case CEPEDA = 'cepeda';
    case OTRO_CANDIDATO = 'otro_candidato';

    /**
     * Textos con los que WhatsApp puede devolver cada botón.
     *
     * Twilio NO envía ButtonPayload en estas plantillas: manda el texto del
     * botón dentro de `Body`. Este mapa es la única fuente de verdad para
     * reconocerlo; antes estaba duplicado en dos controllers con listas
     * distintas entre sí.
     *
     * @return array<string, string> texto en minúsculas => valor del enum
     */
    public static function textMap(): array
    {
        return [
            'paloma valencia' => self::PALOM->value,
            'paloma' => self::PALOM->value,
            'ivan cepeda' => self::CEPEDA->value,
            'iván cepeda' => self::CEPEDA->value,
            'cepeda' => self::CEPEDA->value,
            'otro candidato' => self::OTRO_CANDIDATO->value,
            'otro' => self::OTRO_CANDIDATO->value,
        ];
    }

    /**
     * Reconoce el botón a partir del texto del mensaje entrante.
     *
     * Ignora emojis y espacios sobrantes, y evalúa primero los textos más
     * largos para que "otro candidato" gane sobre "otro".
     */
    public static function fromText(?string $body): ?self
    {
        $normalized = self::normalizeText($body);

        if ($normalized === '') {
            return null;
        }

        $map = self::textMap();

        uksort($map, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($map as $text => $buttonId) {
            if (str_contains($normalized, self::normalizeText($text))) {
                return self::from($buttonId);
            }
        }

        return null;
    }

    /**
     * Deja solo letras, números y espacios simples (fuera emojis y puntuación).
     */
    private static function normalizeText(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Valida si un valor corresponde a un botón válido
     */
    public static function isValid(mixed $value): bool
    {
        return is_string($value) && self::tryFrom($value) !== null;
    }

    /**
     * Obtiene la etiqueta legible del botón
     */
    public function label(): string
    {
        return match ($this) {
            self::PALOM => 'Paloma Valencia',
            self::CEPEDA => 'Iván Cepeda',
            self::OTRO_CANDIDATO => 'Otro Candidato',
        };
    }

    /**
     * Obtiene el nombre del campo de base de datos para conteo
     */
    public function countField(): string
    {
        return match ($this) {
            self::PALOM => 'palom_count',
            self::CEPEDA => 'cepeda_count',
            self::OTRO_CANDIDATO => 'otro_count',
        };
    }

    /**
     * Mensaje de agradecimiento que se envía al capturar la respuesta.
     */
    public function thankYouMessage(): string
    {
        return match ($this) {
            self::PALOM => '✅ ¡Gracias por tu voto! Tu preferencia por Paloma Valencia ha sido registrada. 🕊️',
            self::CEPEDA => '✅ ¡Gracias por tu voto! Tu preferencia por Cepeda ha sido registrada. 🎯',
            self::OTRO_CANDIDATO => '✅ ¡Gracias por tu voto! Tu preferencia ha sido registrada. 🤝',
        };
    }
}
