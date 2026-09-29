<?php

/**
 * This file is part of ModelosIGIC plugin for FacturaScripts.
 * Copyright (C) 2014-2026 Carlos Garcia Gomez <neorazorx@gmail.com>
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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * Clase auxiliar para los cálculos del IGIC (Impuesto General Indirecto Canario).
 *
 * El IGIC es el impuesto indirecto que grava el consumo en las Islas Canarias,
 * equivalente al IVA en la península pero con tipos impositivos diferentes:
 * - Tipo cero: 0%
 * - Tipo reducido: 3%
 * - Tipo general: 7%
 * - Tipo incrementado: 9,5%
 * - Tipo especial incrementado: 15%
 * - Tipo especial: 20% (tabaco)
 *
 * @see https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-420
 */
class IGICHelper
{
    /** @var DataBase */
    protected DataBase $db;

    public function __construct()
    {
        $this->db = new DataBase();
    }

    /**
     * Obtiene el desglose del IGIC de las facturas de compra para un período.
     *
     * @param string $fechaInicio Fecha de inicio del período (formato Y-m-d)
     * @param string $fechaFin    Fecha de fin del período (formato Y-m-d)
     *
     * @return array Array con el desglose por tipo de IGIC
     */
    public function desgloseIGICCompras(string $fechaInicio, string $fechaFin): array
    {
        $desglose = [];

        if (false === $this->db->tableExists('lineasivafactprov')) {
            return $desglose;
        }

        $sql = 'SELECT iva, recargo, SUM(neto) as neto, SUM(totaliva) as totaliva, SUM(totalrecargo) as totalrecargo'
            . ' FROM lineasivafactprov WHERE idfactura IN (SELECT idfactura FROM facturasprov'
            . ' WHERE fecha >= ' . $this->db->var2str($fechaInicio)
            . ' AND fecha <= ' . $this->db->var2str($fechaFin) . ')'
            . ' GROUP BY iva, recargo ORDER BY iva ASC, recargo ASC';

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $desglose[] = [
                    'iva' => (float) $d['iva'],
                    'recargo' => (float) $d['recargo'],
                    'neto' => (float) $d['neto'],
                    'totaliva' => (float) $d['totaliva'],
                    'totalrecargo' => (float) $d['totalrecargo'],
                ];
            }
        }

        return $desglose;
    }

    /**
     * Obtiene el desglose del IGIC de las facturas de venta para un período.
     *
     * @param string $fechaInicio Fecha de inicio del período (formato Y-m-d)
     * @param string $fechaFin    Fecha de fin del período (formato Y-m-d)
     *
     * @return array Array con el desglose por tipo de IGIC
     */
    public function desgloseIGICVentas(string $fechaInicio, string $fechaFin): array
    {
        $desglose = [];

        if (false === $this->db->tableExists('lineasivafactcli')) {
            return $desglose;
        }

        $sql = 'SELECT iva, recargo, SUM(neto) as neto, SUM(totaliva) as totaliva, SUM(totalrecargo) as totalrecargo'
            . ' FROM lineasivafactcli WHERE idfactura IN (SELECT idfactura FROM facturascli'
            . ' WHERE fecha >= ' . $this->db->var2str($fechaInicio)
            . ' AND fecha <= ' . $this->db->var2str($fechaFin) . ')'
            . ' GROUP BY iva, recargo ORDER BY iva ASC, recargo ASC';

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $desglose[] = [
                    'iva' => (float) $d['iva'],
                    'recargo' => (float) $d['recargo'],
                    'neto' => (float) $d['neto'],
                    'totaliva' => (float) $d['totaliva'],
                    'totalrecargo' => (float) $d['totalrecargo'],
                ];
            }
        }

        return $desglose;
    }

    /**
     * Comprueba si hay facturas sin asiento contable en el período.
     *
     * @param string $fechaInicio Fecha de inicio del período
     * @param string $fechaFin    Fecha de fin del período
     *
     * @return bool True si hay facturas sin asiento
     */
    public function hayFacturasSinAsiento(string $fechaInicio, string $fechaFin): bool
    {
        // Facturas de compra sin asiento
        $sql = 'SELECT COUNT(*) as num FROM facturasprov WHERE idasiento IS NULL'
            . ' AND fecha >= ' . $this->db->var2str($fechaInicio)
            . ' AND fecha <= ' . $this->db->var2str($fechaFin);
        $data = $this->db->select($sql);
        if ($data && (int) $data[0]['num'] > 0) {
            return true;
        }

        // Facturas de venta sin asiento
        $sql = 'SELECT COUNT(*) as num FROM facturascli WHERE idasiento IS NULL'
            . ' AND fecha >= ' . $this->db->var2str($fechaInicio)
            . ' AND fecha <= ' . $this->db->var2str($fechaFin);
        $data = $this->db->select($sql);
        if ($data && (int) $data[0]['num'] > 0) {
            return true;
        }

        return false;
    }

    /**
     * Calcula el período trimestral por defecto según la fecha actual.
     *
     * Plazos de presentación del Modelo 420:
     * - T1 (enero-marzo): del 1 al 20 de abril
     * - T2 (abril-junio): del 1 al 20 de julio
     * - T3 (julio-septiembre): del 1 al 20 de octubre
     * - T4 (octubre-diciembre): del 1 al 30 de enero del año siguiente
     *
     * @return array Array con 'periodo', 'fecha_desde' y 'fecha_hasta'
     */
    public function calcularPeriodoActual(): array
    {
        $mes = (int) date('n');
        $anyo = (int) date('Y');

        switch ($mes) {
            case 1:
                // En enero se presenta el T4 del año anterior
                return [
                    'periodo' => 'T4',
                    'fecha_desde' => date('Y-m-d', strtotime(($anyo - 1) . '-10-01')),
                    'fecha_hasta' => date('Y-m-d', strtotime(($anyo - 1) . '-12-31')),
                ];

            case 2:
            case 3:
            case 4:
                return [
                    'periodo' => 'T1',
                    'fecha_desde' => date('Y-01-01'),
                    'fecha_hasta' => date('Y-03-31'),
                ];

            case 5:
            case 6:
            case 7:
                return [
                    'periodo' => 'T2',
                    'fecha_desde' => date('Y-04-01'),
                    'fecha_hasta' => date('Y-06-30'),
                ];

            case 8:
            case 9:
            case 10:
                return [
                    'periodo' => 'T3',
                    'fecha_desde' => date('Y-07-01'),
                    'fecha_hasta' => date('Y-09-30'),
                ];

            default:
                return [
                    'periodo' => 'T4',
                    'fecha_desde' => date('Y-10-01'),
                    'fecha_hasta' => date('Y-12-31'),
                ];
        }
    }

    /**
     * Obtiene las fechas para un período específico.
     *
     * @param string $periodo Código del período (T1, T2, T3, T4)
     * @param int    $anyo    Año del período
     *
     * @return array Array con 'fecha_desde' y 'fecha_hasta'
     */
    public function fechasPorPeriodo(string $periodo, int $anyo): array
    {
        return match ($periodo) {
            'T1' => [
                'fecha_desde' => $anyo . '-01-01',
                'fecha_hasta' => $anyo . '-03-31',
            ],
            'T2' => [
                'fecha_desde' => $anyo . '-04-01',
                'fecha_hasta' => $anyo . '-06-30',
            ],
            'T3' => [
                'fecha_desde' => $anyo . '-07-01',
                'fecha_hasta' => $anyo . '-09-30',
            ],
            'T4' => [
                'fecha_desde' => $anyo . '-10-01',
                'fecha_hasta' => $anyo . '-12-31',
            ],
            default => [
                'fecha_desde' => $anyo . '-01-01',
                'fecha_hasta' => $anyo . '-12-31',
            ],
        };
    }

    /**
     * Calcula el resumen del IGIC para la previsualización del asiento.
     *
     * @param string $fechaInicio  Fecha de inicio del período
     * @param string $fechaFin     Fecha de fin del período
     * @param string $codEjercicio Código del ejercicio
     *
     * @return array Array con las partidas propuestas para el asiento
     */
    public function calcularRegularizacion(string $fechaInicio, string $fechaFin, string $codEjercicio): array
    {
        $partidas = [];
        $saldo = 0.0;

        // Obtener IGIC soportado (equivalente a IVA soportado)
        foreach ($this->getSubcuentasEspeciales('IVASOP', $codEjercicio) as $sctaIGICSop) {
            $totales = $this->getTotalesSubcuenta($sctaIGICSop->idsubcuenta, $fechaInicio, $fechaFin);

            if ($totales['saldo'] != 0) {
                // Invertimos el debe y el haber para la regularización
                $partidas[] = [
                    'subcuenta' => $sctaIGICSop,
                    'debe' => $totales['haber'],
                    'haber' => $totales['debe'],
                ];
                $saldo += $totales['haber'] - $totales['debe'];
            }
        }

        // Obtener IGIC repercutido (equivalente a IVA repercutido)
        foreach ($this->getSubcuentasEspeciales('IVAREP', $codEjercicio) as $sctaIGICRep) {
            $totales = $this->getTotalesSubcuenta($sctaIGICRep->idsubcuenta, $fechaInicio, $fechaFin);

            if ($totales['saldo'] != 0) {
                // Invertimos el debe y el haber para la regularización
                $partidas[] = [
                    'subcuenta' => $sctaIGICRep,
                    'debe' => $totales['haber'],
                    'haber' => $totales['debe'],
                ];
                $saldo += $totales['haber'] - $totales['debe'];
            }
        }

        // Añadir la partida de cierre (acreedor o deudor)
        if ($saldo > 0) {
            // Resultado positivo: a pagar (Hacienda Pública acreedora)
            $sctaAcr = $this->getSubcuentaEspecial('IVAACR', $codEjercicio);
            if ($sctaAcr) {
                $partidas[] = [
                    'subcuenta' => $sctaAcr,
                    'debe' => 0,
                    'haber' => $saldo,
                ];
            }
        } elseif ($saldo < 0) {
            // Resultado negativo: a compensar o devolver (Hacienda Pública deudora)
            $sctaDeu = $this->getSubcuentaEspecial('IVADEU', $codEjercicio);
            if ($sctaDeu) {
                $partidas[] = [
                    'subcuenta' => $sctaDeu,
                    'debe' => abs($saldo),
                    'haber' => 0,
                ];
            }
        }

        return $partidas;
    }

    /**
     * Obtiene las subcuentas asociadas a una cuenta especial.
     *
     * @param string $codCuentaEsp Código de la cuenta especial (IVASOP, IVAREP, etc.)
     * @param string $codEjercicio Código del ejercicio
     *
     * @return Subcuenta[] Array de subcuentas
     */
    public function getSubcuentasEspeciales(string $codCuentaEsp, string $codEjercicio): array
    {
        $subcuentas = [];

        $sql = 'SELECT s.* FROM subcuentas s'
            . ' INNER JOIN cuentas c ON s.idcuenta = c.idcuenta'
            . ' INNER JOIN cuentasesp ce ON c.codcuentaesp = ce.codcuentaesp'
            . ' WHERE ce.codcuentaesp = ' . $this->db->var2str($codCuentaEsp)
            . ' AND s.codejercicio = ' . $this->db->var2str($codEjercicio);

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $row) {
                $subcuenta = new Subcuenta($row);
                $subcuentas[] = $subcuenta;
            }
        }

        return $subcuentas;
    }

    /**
     * Obtiene una subcuenta asociada a una cuenta especial.
     *
     * @param string $codCuentaEsp Código de la cuenta especial
     * @param string $codEjercicio Código del ejercicio
     *
     * @return Subcuenta|null La primera subcuenta encontrada o null
     */
    public function getSubcuentaEspecial(string $codCuentaEsp, string $codEjercicio): ?Subcuenta
    {
        $subcuentas = $this->getSubcuentasEspeciales($codCuentaEsp, $codEjercicio);
        return empty($subcuentas) ? null : $subcuentas[0];
    }

    /**
     * Obtiene los totales de una subcuenta para un rango de fechas.
     *
     * @param int    $idsubcuenta ID de la subcuenta
     * @param string $fechaInicio Fecha de inicio
     * @param string $fechaFin    Fecha de fin
     *
     * @return array Array con 'debe', 'haber' y 'saldo'
     */
    public function getTotalesSubcuenta(int $idsubcuenta, string $fechaInicio, string $fechaFin): array
    {
        $result = ['debe' => 0.0, 'haber' => 0.0, 'saldo' => 0.0];

        $sql = 'SELECT COALESCE(SUM(debe), 0) as debe, COALESCE(SUM(haber), 0) as haber'
            . ' FROM partidas p'
            . ' INNER JOIN asientos a ON p.idasiento = a.idasiento'
            . ' WHERE p.idsubcuenta = ' . (int) $idsubcuenta
            . ' AND a.fecha >= ' . $this->db->var2str($fechaInicio)
            . ' AND a.fecha <= ' . $this->db->var2str($fechaFin);

        $data = $this->db->select($sql);
        if ($data) {
            $result['debe'] = (float) $data[0]['debe'];
            $result['haber'] = (float) $data[0]['haber'];
            $result['saldo'] = $result['debe'] - $result['haber'];
        }

        return $result;
    }

    /**
     * Calcula el total del IGIC devengado (repercutido en ventas).
     *
     * @param array $desgloseVentas Array del desglose de ventas
     *
     * @return float Total IGIC devengado
     */
    public function calcularTotalDevengado(array $desgloseVentas): float
    {
        $total = 0.0;
        foreach ($desgloseVentas as $item) {
            $total += $item['totaliva'] + $item['totalrecargo'];
        }
        return $total;
    }

    /**
     * Calcula el total del IGIC deducible (soportado en compras).
     *
     * @param array $desgloseCompras Array del desglose de compras
     *
     * @return float Total IGIC deducible
     */
    public function calcularTotalDeducible(array $desgloseCompras): float
    {
        $total = 0.0;
        foreach ($desgloseCompras as $item) {
            $total += $item['totaliva'] + $item['totalrecargo'];
        }
        return $total;
    }

    /**
     * Obtiene el nombre descriptivo del tipo de IGIC.
     *
     * @param float $tipo Porcentaje del tipo de IGIC
     *
     * @return string Nombre descriptivo
     */
    public function nombreTipoIGIC(float $tipo): string
    {
        return match (true) {
            $tipo == 0 => Tools::lang()->trans('igic-tipo-cero'),
            $tipo == 3 => Tools::lang()->trans('igic-tipo-reducido'),
            $tipo == 7 => Tools::lang()->trans('igic-tipo-general'),
            $tipo == 9.5 => Tools::lang()->trans('igic-tipo-incrementado'),
            $tipo == 15 => Tools::lang()->trans('igic-tipo-especial-incrementado'),
            $tipo == 20 => Tools::lang()->trans('igic-tipo-especial'),
            default => $tipo . '%',
        };
    }
}
