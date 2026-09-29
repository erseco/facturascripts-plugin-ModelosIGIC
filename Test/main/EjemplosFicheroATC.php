<?php

/**
 * This file is part of ModelosIGIC plugin for FacturaScripts.
 * Copyright (C) 2026 Ernesto Serrano <info@ernesto.es>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace FacturaScripts\Test\Plugins;

/**
 * Casos de ejemplo del fichero para el programa de ayuda del 420, con datos ficticios.
 *
 * Los ficheros de doc/ejemplos/ se generan con estos casos (ATCFicheroOficialTest) y se han
 * comprobado con el programa de ayuda oficial (Test/atc/validar.sh).
 */
final class EjemplosFicheroATC
{
    /** Sujeto pasivo ficticio: NIF con dígito de control válido y dirección inventada. */
    public const SUJETO = [
        'nif' => 'B00000000',
        'nrs' => 'Empresa de Ejemplo Canarias, S.L.',
        'svp' => 'CL',
        'nvp' => 'Calle de Ejemplo',
        'npk' => '1',
        'pop' => '35',
        'cmu' => '35016',
        'cp' => '35001',
    ];

    /**
     * Casos: nombre => [período, casillas (CasillasModelo420::calcular()), datos del formulario].
     */
    public static function casos(): array
    {
        return [
            'ingresar' => ['T1', self::casillas(
                [
                    ['tipo' => 3.0, 'base' => 2000.0, 'cuota' => 60.0],
                    ['tipo' => 7.0, 'base' => 10000.0, 'cuota' => 700.0],
                ],
                4000.0,
                280.0,
                'I'
            ), self::SUJETO + ['fpa' => '5']],
            'compensar' => ['T2', self::casillas(
                [['tipo' => 7.0, 'base' => 1000.0, 'cuota' => 70.0]],
                3000.0,
                210.0,
                'C'
            ), self::SUJETO],
            'devolver' => ['T4', self::casillas(
                [['tipo' => 0.0, 'base' => 500.0, 'cuota' => 0.0], ['tipo' => 7.0, 'base' => 1000.0, 'cuota' => 70.0]],
                3000.0,
                210.0,
                'C'
            ), self::SUJETO + ['tipo' => 'D', 'iban' => 'ES9121000418450200051332']],
            'sin-actividad' => ['T3', self::casillas([], 0.0, 0.0, 'S'), self::SUJETO],
        ];
    }

    /**
     * Casillas con la misma estructura que CasillasModelo420::calcular().
     */
    public static function casillas(array $filas, float $c26, float $c27, string $resultado): array
    {
        $c25 = 0.0;
        foreach ($filas as $fila) {
            $c25 += $fila['cuota'];
        }

        return [
            'filas' => $filas,
            'casillas' => [
                '25' => ['importe' => $c25],
                '26' => ['importe' => $c26],
                '27' => ['importe' => $c27],
                '40' => ['importe' => $c27],
                '41' => ['importe' => round($c25 - $c27, 2)],
                '45' => ['importe' => round($c25 - $c27, 2)],
            ],
            'resultado' => $resultado,
        ];
    }
}
