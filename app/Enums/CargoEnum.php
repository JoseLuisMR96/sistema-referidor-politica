<?php

namespace App\Enums;

/**
 * Cargo al que se postula el candidato (tenant).
 *
 * El ámbito define su circunscripción, es decir, dónde lo pueden votar:
 * - nacional: todo el país (no se registra lugar);
 * - departamental: un departamento (se analiza por sus municipios);
 * - municipal: un municipio (se registran departamento y municipio).
 */
enum CargoEnum: string
{
    case PRESIDENCIA = 'presidencia';
    case SENADO = 'senado';
    case CAMARA = 'camara';
    case GOBERNACION = 'gobernacion';
    case ASAMBLEA = 'asamblea';
    case ALCALDIA = 'alcaldia';
    case CONCEJO = 'concejo';
    case JAL = 'jal';

    public const AMBITO_NACIONAL = 'nacional';

    public const AMBITO_DEPARTAMENTAL = 'departamental';

    public const AMBITO_MUNICIPAL = 'municipal';

    public function label(): string
    {
        return match ($this) {
            self::PRESIDENCIA => 'Presidencia',
            self::SENADO => 'Senado',
            self::CAMARA => 'Cámara de Representantes',
            self::GOBERNACION => 'Gobernación',
            self::ASAMBLEA => 'Asamblea (diputado)',
            self::ALCALDIA => 'Alcaldía',
            self::CONCEJO => 'Concejo municipal',
            self::JAL => 'Junta Administradora Local (edil)',
        };
    }

    public function ambito(): string
    {
        return match ($this) {
            self::PRESIDENCIA, self::SENADO => self::AMBITO_NACIONAL,
            self::CAMARA, self::GOBERNACION, self::ASAMBLEA => self::AMBITO_DEPARTAMENTAL,
            self::ALCALDIA, self::CONCEJO, self::JAL => self::AMBITO_MUNICIPAL,
        };
    }

    public function requiereDepartamento(): bool
    {
        return $this->ambito() !== self::AMBITO_NACIONAL;
    }

    public function requiereMunicipio(): bool
    {
        return $this->ambito() === self::AMBITO_MUNICIPAL;
    }
}
