<?php

namespace App\Services\Redes\Temas;

/**
 * Clasifica el texto de publicaciones de candidatos en los temas fijos de
 * RedPublicacion::TEMAS.
 *
 * Interfaz para poder cambiar el proveedor y probar sin llamar a la API real
 * (los tests enlazan una implementación falsa).
 */
interface ClasificadorTemas
{
    public function configurado(): bool;

    /**
     * @param  list<string>  $textos  texto de cada publicación
     * @return list<string> una clave de RedPublicacion::TEMAS por texto, en el mismo orden
     */
    public function clasificar(array $textos): array;
}
