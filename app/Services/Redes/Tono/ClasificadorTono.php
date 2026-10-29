<?php

namespace App\Services\Redes\Tono;

/**
 * Clasifica comentarios por su postura frente a un candidato.
 *
 * Interfaz para poder cambiar el proveedor y probar sin llamar a la API real
 * (los tests enlazan una implementación falsa).
 */
interface ClasificadorTono
{
    public const A_FAVOR = 'a_favor';

    public const EN_CONTRA = 'en_contra';

    public const NEUTRO = 'neutro';

    public const TONOS = [self::A_FAVOR, self::EN_CONTRA, self::NEUTRO];

    public function configurado(): bool;

    /**
     * @param  list<string>  $comentarios  solo el texto, sin autor
     * @return array{tonos: list<string>, temas: list<string>}
     *                                                         `tonos` en el mismo orden y cantidad que `$comentarios`
     */
    public function clasificar(string $candidato, string $postulacion, array $comentarios): array;
}
