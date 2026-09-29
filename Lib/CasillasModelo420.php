<?php

/**
 * This file is part of ModelosIGIC plugin for FacturaScripts.
 * Copyright (C) 2026 Ernesto Serrano <info@ernesto.es>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\ModelosIGIC\Lib;

use FacturaScripts\Core\Tools;

/**
 * Casillas del Modelo 420 que se pueden obtener de las facturas de FacturaScripts.
 *
 * Fuente: Instrucciones del modelo 420 publicadas por la ATC (modelo aprobado por la
 * Resolución de 15/01/2020, BOC n.º 16 de 24/01/2020), apartado 7 «Liquidación».
 * Cada casilla lleva su estado:
 * - «calculada»: se obtiene de las facturas con IGIC del período.
 * - «parcial»: es una suma oficial en la que intervienen casillas que el plugin no calcula.
 * - «no-calculada»: depende de datos que FacturaScripts no registra; hay que revisarla y
 *   rellenarla en el programa de ayuda de la ATC.
 */
class CasillasModelo420
{
    public const CALCULADA = 'calculada';
    public const NO_CALCULADA = 'no-calculada';
    public const PARCIAL = 'parcial';

    /**
     * Filas de IGIC devengado: base, tipo y cuota (Instrucciones 420, apdo. 7).
     *
     * Las seis primeras son las del modelo aprobado en 2020. Las filas 16b–18b y 16c–18c solo
     * figuran en el programa de ayuda de 2026 (v9.3.0) y su manual de 16/02/2026; no se ha
     * localizado la resolución del BOC que las aprueba (doc/NORMATIVA.md, pendiente 1).
     */
    public const FILAS_DEVENGADO = [
        ['01', '02', '03'],
        ['04', '05', '06'],
        ['07', '08', '09'],
        ['10', '11', '12'],
        ['13', '14', '15'],
        ['16', '17', '18'],
        ['16b', '17b', '18b'],
        ['16c', '17c', '18c'],
    ];

    /** Casillas que el plugin no calcula, con su concepto (Instrucciones 420, apdos. 7 y 8). */
    public const NO_CALCULADAS = [
        '19-20' => 'casilla-420-inversion-sujeto-pasivo',
        '21-22' => 'casilla-420-modificacion-bases',
        '23-24' => 'casilla-420-regimen-viajeros',
        '28-29' => 'casilla-420-interiores-inversion',
        '30-31' => 'casilla-420-importaciones-corrientes',
        '32-33' => 'casilla-420-importaciones-inversion',
        '34-35' => 'casilla-420-rectificacion-deducciones',
        '36' => 'casilla-420-compensaciones-reagp',
        '37' => 'casilla-420-regularizacion-inversion',
        '38' => 'casilla-420-regularizacion-inicio',
        '39' => 'casilla-420-regularizacion-prorrata',
        '42' => 'casilla-420-regularizacion-22-8',
        '43' => 'casilla-420-cuotas-compensar',
        '44' => 'casilla-420-complementaria',
        '46' => 'casilla-420-exportaciones-exentas',
        '47' => 'casilla-420-no-sujetas',
        '48-51' => 'casilla-420-criterio-caja',
    ];

    /** @var IGICHelper */
    protected IGICHelper $helper;

    public function __construct(?IGICHelper $helper = null)
    {
        $this->helper = $helper ?? new IGICHelper();
    }

    /**
     * Calcula las casillas del modelo a partir del desglose de ventas y compras del período.
     *
     * @param array  $desgloseVentas  Desglose por tipo de las ventas con IGIC
     * @param array  $desgloseCompras Desglose por tipo de las compras con IGIC
     * @param string $fecha           Fecha de fin del período, para la denominación de los tipos
     */
    public function calcular(array $desgloseVentas, array $desgloseCompras, string $fecha): array
    {
        $filas = $this->filasDevengado($desgloseVentas, $fecha);

        // casilla 25 = 03+06+09+12+15+18+20+22-24 (Instrucciones 420); el programa 2026 añade 18b y 18c
        $c25 = 0.0;
        foreach ($filas as $fila) {
            $c25 += $fila['cuota'];
        }
        $c25 = Tools::round($c25);

        // casillas 26 y 27: adquisiciones interiores de bienes y servicios corrientes
        $c26 = 0.0;
        foreach ($desgloseCompras as $item) {
            $c26 += (float) $item['neto'];
        }
        $c26 = Tools::round($c26);
        $c27 = $this->helper->calcularTotalDeducible($desgloseCompras);

        // casilla 40 = 27+29+31+33+35+36+37+38+39; 41 = 25-40; 45 = 41+42-43-44
        $c40 = $c27;
        $c41 = Tools::round($c25 - $c40);
        $c45 = $c41;

        return [
            'filas' => $filas,
            'sinCasilla' => count(array_filter($filas, static fn (array $fila) => null === $fila['casillas'])),
            'casillas' => [
                '25' => ['importe' => $c25, 'estado' => self::PARCIAL],
                '26' => ['importe' => $c26, 'estado' => self::CALCULADA],
                '27' => ['importe' => $c27, 'estado' => self::CALCULADA],
                '40' => ['importe' => $c40, 'estado' => self::PARCIAL],
                '41' => ['importe' => $c41, 'estado' => self::PARCIAL],
                '45' => ['importe' => $c45, 'estado' => self::PARCIAL],
            ],
            'noCalculadas' => self::NO_CALCULADAS,
            'resultado' => $this->tipoResultado($c45, empty($filas) && empty($desgloseCompras)),
            'recargo' => Tools::round(
                $this->helper->calcularTotalRecargo($desgloseVentas)
                + $this->helper->calcularTotalRecargo($desgloseCompras)
            ),
        ];
    }

    /**
     * Asigna cada tipo de IGIC devengado a una fila de casillas (base, tipo, cuota).
     *
     * Las instrucciones piden una fila por cada tipo aplicado, incluido el tipo cero, sin fijar
     * el orden; el plugin las ordena de menor a mayor tipo. Si hay más tipos que filas, las
     * sobrantes quedan sin casilla y la vista lo avisa.
     */
    public function filasDevengado(array $desgloseVentas, string $fecha): array
    {
        $porTipo = [];
        foreach ($desgloseVentas as $item) {
            $key = (string) (float) $item['iva'];
            if (false === isset($porTipo[$key])) {
                $porTipo[$key] = ['tipo' => (float) $item['iva'], 'base' => 0.0, 'cuota' => 0.0];
            }

            $porTipo[$key]['base'] += (float) $item['neto'];
            $porTipo[$key]['cuota'] += (float) $item['totaliva'];
        }

        usort($porTipo, static fn (array $a, array $b): int => $a['tipo'] <=> $b['tipo']);

        $filas = [];
        foreach (array_values($porTipo) as $i => $item) {
            $filas[] = [
                'casillas' => self::FILAS_DEVENGADO[$i] ?? null,
                'programa2026' => $i >= 6 && isset(self::FILAS_DEVENGADO[$i]),
                'tipo' => $item['tipo'],
                'denominacion' => $this->helper->nombreTipoIGIC($item['tipo'], $fecha),
                'vigente' => $this->helper->esTipoVigente($item['tipo'], $fecha),
                'base' => Tools::round($item['base']),
                'cuota' => Tools::round($item['cuota']),
            ];
        }

        return $filas;
    }

    /**
     * Tipo de resultado de la autoliquidación según la casilla 45 (Instrucciones 420, apdos. 3–5).
     *
     * - Positivo: a ingresar (I).
     * - Negativo o cero: a compensar (C). Si es negativo, en el 4T se puede optar por la
     *   devolución (D); esa opción la elige el declarante.
     * - Sin actividad (S): el plugin solo lo propone si en el período no hay ninguna operación
     *   con IGIC; las instrucciones lo prevén cuando no se ha devengado ni soportado cuota alguna.
     */
    public function tipoResultado(float $casilla45, bool $sinOperaciones = false): string
    {
        if ($sinOperaciones && 0.0 === $casilla45) {
            return 'S';
        }

        return $casilla45 > 0 ? 'I' : 'C';
    }
}
