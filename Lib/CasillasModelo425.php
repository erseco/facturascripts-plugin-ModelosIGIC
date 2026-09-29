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
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;

/**
 * Casillas del Modelo 425 que se pueden obtener de las facturas de FacturaScripts.
 *
 * Fuente: Instrucciones del modelo 425 publicadas por la ATC (modelo modificado por la
 * Resolución de 18/11/2024, BOC n.º 241 de 03/12/2024), apartados 5, 7, 8 y 9. Los estados
 * de las casillas son los de CasillasModelo420.
 */
class CasillasModelo425
{
    /**
     * Filas del régimen ordinario (Instrucciones 425, apdo. 5: «casillas de la 01 a 18 bis»).
     *
     * Las instrucciones no detallan la numeración de la fila «18 bis», así que el plugin solo
     * numera las seis filas 01–18 (doc/NORMATIVA.md, pendiente de verificar).
     */
    public const FILAS_REGIMEN_ORDINARIO = [
        ['01', '02', '03'],
        ['04', '05', '06'],
        ['07', '08', '09'],
        ['10', '11', '12'],
        ['13', '14', '15'],
        ['16', '17', '18'],
    ];

    /** Casillas que el plugin no calcula, con su concepto (Instrucciones 425). */
    public const NO_CALCULADAS = [
        '19-33' => 'casilla-425-bienes-usados',
        '34-48' => 'casilla-425-objetos-arte',
        '49-66 bis' => 'casilla-425-criterio-caja',
        '67-69' => 'casilla-425-agencias-viajes',
        '70-73' => 'casilla-425-modificacion-bases',
        '75-76' => 'casilla-425-inversion-sujeto-pasivo',
        '77-78' => 'casilla-425-regimen-viajeros',
        '82-93' => 'casilla-425-otras-deducciones',
        '96-111' => 'casilla-425-regimen-simplificado',
        '112' => 'casilla-425-regularizacion-22-8',
        '114' => 'casilla-425-compensar-anterior',
        '117-119' => 'casilla-425-redeme-compensar-devolver',
        '121-133' => 'casilla-425-operaciones-especificas',
        '135-147' => 'casilla-425-otras-informativas',
    ];

    /** @var CasillasModelo420 */
    protected CasillasModelo420 $casillas420;

    /** @var IGICHelper */
    protected IGICHelper $helper;

    public function __construct(?IGICHelper $helper = null)
    {
        $this->helper = $helper ?? new IGICHelper();
        $this->casillas420 = new CasillasModelo420($this->helper);
    }

    /**
     * Calcula las casillas del resumen anual.
     *
     * @param array             $desgloseVentas  Desglose por tipo de las ventas con IGIC del año
     * @param array             $desgloseCompras Desglose por tipo de las compras con IGIC del año
     * @param string            $fecha           Fecha de fin del ejercicio
     * @param DeclaracionIGIC[] $modelos420      Autoliquidaciones 420 del ejercicio registradas
     */
    public function calcular(array $desgloseVentas, array $desgloseCompras, string $fecha, array $modelos420): array
    {
        $filas = $this->casillas420->filasDevengado($desgloseVentas, $fecha);
        foreach ($filas as $i => $fila) {
            $filas[$i]['casillas'] = self::FILAS_REGIMEN_ORDINARIO[$i] ?? null;
            $filas[$i]['programa2026'] = false;
        }

        $bases = 0.0;
        $cuotas = 0.0;
        foreach ($filas as $fila) {
            $bases += $fila['base'];
            $cuotas += $fila['cuota'];
        }

        $c80 = 0.0;
        foreach ($desgloseCompras as $item) {
            $c80 += (float) $item['neto'];
        }

        // 74 = 01+04+...+16+19+...+70-72; 79 = 03+06+...+18+21+...+71-73+76-78
        $c74 = Tools::round($bases);
        $c79 = Tools::round($cuotas);
        // 80 y 81: operaciones interiores corrientes; 94 = 81+83+85+87+89+90+91+92+93
        $c80 = Tools::round($c80);
        $c81 = $this->helper->calcularTotalDeducible($desgloseCompras);
        $c94 = $c81;
        // 95 = 79-94; 113 = 95+111; 115 = 112+113-114
        $c95 = Tools::round($c79 - $c94);

        return [
            'filas' => $filas,
            'sinCasilla' => count(array_filter($filas, static fn (array $fila) => null === $fila['casillas'])),
            'casillas' => [
                '74' => ['importe' => $c74, 'estado' => CasillasModelo420::PARCIAL],
                '79' => ['importe' => $c79, 'estado' => CasillasModelo420::PARCIAL],
                '80' => ['importe' => $c80, 'estado' => CasillasModelo420::CALCULADA],
                '81' => ['importe' => $c81, 'estado' => CasillasModelo420::CALCULADA],
                '94' => ['importe' => $c94, 'estado' => CasillasModelo420::PARCIAL],
                '95' => ['importe' => $c95, 'estado' => CasillasModelo420::PARCIAL],
                '113' => ['importe' => $c95, 'estado' => CasillasModelo420::PARCIAL],
                '115' => ['importe' => $c95, 'estado' => CasillasModelo420::PARCIAL],
                '116' => ['importe' => $this->totalIngresado($modelos420), 'estado' => CasillasModelo420::PARCIAL],
                '120' => ['importe' => $c74, 'estado' => CasillasModelo420::PARCIAL],
            ],
            'noCalculadas' => self::NO_CALCULADAS,
            'recargo' => Tools::round(
                $this->helper->calcularTotalRecargo($desgloseVentas)
                + $this->helper->calcularTotalRecargo($desgloseCompras)
            ),
        ];
    }

    /**
     * Casilla 116: suma de las cantidades a ingresar de las autoliquidaciones periódicas del
     * ejercicio (Instrucciones 425, apdo. 8).
     *
     * El plugin solo conoce las autoliquidaciones 420 registradas en él. Las rectificadas se
     * sustituyen por su rectificativa, así que no se suman.
     *
     * @param DeclaracionIGIC[] $modelos420
     */
    public function totalIngresado(array $modelos420): float
    {
        $total = 0.0;
        foreach ($modelos420 as $modelo) {
            if ($modelo->tipo === '420' && $modelo->estado !== 'rectificado' && $modelo->resultado > 0) {
                $total += (float) $modelo->resultado;
            }
        }

        return Tools::round($total);
    }
}
