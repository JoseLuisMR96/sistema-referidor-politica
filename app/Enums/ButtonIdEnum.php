<?php

namespace App\Enums;

enum ButtonIdEnum: string
{
    case PALOM = 'palom';
    case CEPEDA = 'cepeda';
    case OTRO_CANDIDATO = 'otro_candidato';

    /**
     * Valida si un valor corresponde a un botón válido
     */
    public static function isValid(mixed $value): bool
    {
        return collect(self::cases())
            ->pluck('value')
            ->contains($value);
    }

    /**
     * Obtiene la etiqueta legible del botón
     */
    public function label(): string
    {
        return match ($this) {
            self::PALOM => 'Palom',
            self::CEPEDA => 'Cepeda',
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
}
