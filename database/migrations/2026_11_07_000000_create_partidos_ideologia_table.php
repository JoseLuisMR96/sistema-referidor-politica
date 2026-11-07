<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ideología de cada partido (por Partidos::clave()), para la sección
 * "Tendencia política" del Análisis electoral. Es global (no lleva tenant_id)
 * y se edita desde la misma pantalla.
 *
 * Clasificación inicial: la que dan Wikipedia y la prensa colombiana (2026).
 * Es una aproximación y el usuario la corrige cuando quiera.
 */
return new class extends Migration
{
    private const INICIAL = [
        'PACTO HISTÓRICO' => ['izquierda', 'Coalición de izquierda de Gustavo Petro.'],
        'COLOMBIA HUMANA' => ['izquierda', 'Partido de Gustavo Petro.'],
        'POLO DEMOCRÁTICO' => ['izquierda', null],
        'UNIÓN PATRIÓTICA' => ['izquierda', null],
        'COMUNES' => ['izquierda', 'Antes FARC.'],
        'MAIS' => ['izquierda', 'Movimiento Alternativo Indígena y Social.'],
        'FUERZA CIUDADANA' => ['centro_izquierda', 'Carlos Caicedo; cercano al Pacto Histórico.'],
        'ALIANZA VERDE' => ['centro_izquierda', null],
        'PARTIDO LIBERAL' => ['centro_izquierda', 'Liberalismo social; en la práctica muy variable por región.'],
        'ALIANZA SOCIAL INDEPENDIENTE (ASI)' => ['centro_izquierda', null],
        'AICO' => ['centro_izquierda', 'Autoridades Indígenas de Colombia.'],
        'DIGNIDAD & COMPROMISO' => ['centro', 'Antes Dignidad (Jorge Robledo) y Compromiso Ciudadano (Fajardo).'],
        'NUEVO LIBERALISMO' => ['centro', null],
        'EN MARCHA' => ['centro', 'Juan Fernando Cristo.'],
        'GENTE EN MOVIMIENTO' => ['centro', null],
        'PARTIDO DE LA U' => ['centro_derecha', null],
        'CAMBIO RADICAL' => ['centro_derecha', null],
        'MIRA' => ['centro_derecha', 'Partido cristiano.'],
        'OPCIÓN CIUDADANA' => ['centro_derecha', null],
        'LIGA DE GOBERNANTES ANTICORRUPCIÓN' => ['centro_derecha', 'Populismo anticorrupción de Rodolfo Hernández; en 2026 pasó a llamarse Partido Republicano y giró más a la derecha.'],
        'PARTIDO CONSERVADOR' => ['derecha', null],
        'CENTRO DEMOCRÁTICO' => ['derecha', 'Uribismo.'],
        'COLOMBIA JUSTA LIBRES' => ['derecha', 'Partido cristiano.'],
        'SALVACIÓN NACIONAL' => ['derecha', null],
        'CREEMOS' => ['derecha', 'Federico Gutiérrez.'],
        'NUEVA FUERZA DEMOCRÁTICA' => ['derecha', 'Andrés Pastrana.'],
        'DEFENSORES DE LA PATRIA' => ['derecha', 'Abelardo de la Espriella; personería jurídica del CNE en agosto de 2026.'],
    ];

    public function up(): void
    {
        Schema::create('partidos_ideologia', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 191)->unique();
            $table->string('ideologia', 20); // izquierda, centro_izquierda, centro, centro_derecha, derecha
            $table->string('nota', 500)->nullable();
            $table->timestamps();
        });

        $ahora = now();
        DB::table('partidos_ideologia')->insert(collect(self::INICIAL)->map(fn ($v, $clave) => [
            'clave' => $clave, 'ideologia' => $v[0], 'nota' => $v[1],
            'created_at' => $ahora, 'updated_at' => $ahora,
        ])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('partidos_ideologia');
    }
};
