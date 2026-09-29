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
use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Lib\OperacionIVA;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Impuesto;
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
    /**
     * Entrada en vigor del texto refundido (DL 1/2025): disposición final única, día siguiente
     * a su publicación en el BOC n.º 207 de 20/10/2025. Desde entonces el 3 % se denomina
     * «superreducido» y el 5 % «reducido» (exposición de motivos del DL 1/2025).
     */
    public const FECHA_TEXTO_REFUNDIDO = '2025-10-21';

    /**
     * Efectos del tipo específico del 1 % creado por la Ley 9/2025 (disposición final novena,
     * BOC n.º 256 de 29/12/2025): 01/01/2026.
     */
    public const FECHA_TIPO_ESPECIFICO = '2026-01-01';

    /** Periodos de liquidación del Modelo 420 (Decreto 268/2011, art. 57.5: trimestre natural). */
    public const PERIODOS_420 = ['T1', 'T2', 'T3', 'T4'];

    /** @var DataBase */
    protected DataBase $db;

    /** @var Impuesto[] */
    private array $impuestos = [];

    public function __construct()
    {
        $this->db = new DataBase();
    }

    /**
     * Obtiene el desglose del IGIC de las facturas de compra para un período.
     *
     * @param string   $fechaInicio Fecha de inicio del período (formato Y-m-d)
     * @param string   $fechaFin    Fecha de fin del período (formato Y-m-d)
     * @param int|null $idempresa   Empresa de las facturas (todas si es null)
     *
     * @return array Array con el desglose por tipo de IGIC
     */
    public function desgloseIGICCompras(string $fechaInicio, string $fechaFin, ?int $idempresa = null): array
    {
        return $this->analizar($this->facturasProveedor($fechaInicio, $fechaFin, $idempresa))['igic'];
    }

    /**
     * Resumen de las líneas de compra del período que no se incluyen en el cálculo del IGIC.
     */
    public function excluidasCompras(string $fechaInicio, string $fechaFin, ?int $idempresa = null): array
    {
        return $this->analizar($this->facturasProveedor($fechaInicio, $fechaFin, $idempresa))['excluidas'];
    }

    /**
     * Obtiene el desglose del IGIC de las facturas de venta para un período.
     *
     * @param string   $fechaInicio Fecha de inicio del período (formato Y-m-d)
     * @param string   $fechaFin    Fecha de fin del período (formato Y-m-d)
     * @param int|null $idempresa   Empresa de las facturas (todas si es null)
     *
     * @return array Array con el desglose por tipo de IGIC
     */
    public function desgloseIGICVentas(string $fechaInicio, string $fechaFin, ?int $idempresa = null): array
    {
        return $this->analizar($this->facturasCliente($fechaInicio, $fechaFin, $idempresa))['igic'];
    }

    /**
     * Resumen de las líneas de venta del período que no se incluyen en el cálculo del IGIC.
     */
    public function excluidasVentas(string $fechaInicio, string $fechaFin, ?int $idempresa = null): array
    {
        return $this->analizar($this->facturasCliente($fechaInicio, $fechaFin, $idempresa))['excluidas'];
    }

    /**
     * Comprueba si hay facturas sin asiento contable en el período.
     *
     * @param string   $fechaInicio Fecha de inicio del período
     * @param string   $fechaFin    Fecha de fin del período
     * @param int|null $idempresa   Empresa de las facturas (todas si es null)
     *
     * @return bool True si hay facturas sin asiento
     */
    public function hayFacturasSinAsiento(string $fechaInicio, string $fechaFin, ?int $idempresa = null): bool
    {
        $where = static::wherePeriodo($fechaInicio, $fechaFin, $idempresa);
        $where[] = Where::isNull('idasiento');

        return FacturaProveedor::count($where) > 0 || FacturaCliente::count($where) > 0;
    }

    /**
     * Separa las líneas de las facturas en IGIC y excluidas, y agrupa las de IGIC por tipo.
     *
     * Solo entran en el cálculo las líneas cuyo impuesto tiene la operación IGIC del núcleo
     * (OperacionIVA::ES_OPERATION_03) y que no tienen causa de exención. Las causas de exención
     * del núcleo citan la Ley del IVA, no la Ley 20/1991, así que no se asignan a ninguna casilla
     * del modelo: se informan aparte para que el usuario las revise (doc/NORMATIVA.md).
     *
     * Los importes de cada factura se calculan con Calculator::getSubtotals(), el mismo cálculo
     * que hace el núcleo para los totales de la factura (descuentos globales incluidos).
     *
     * @param BusinessDocument[] $facturas
     *
     * @return array{igic: array, excluidas: array}
     */
    protected function analizar(array $facturas): array
    {
        $desglose = [];
        $excluidas = [];
        foreach ($facturas as $factura) {
            $lineasIGIC = [];
            $lineasExcluidas = [];
            foreach ($factura->getLines() as $linea) {
                if ($linea->suplido || empty($linea->pvptotal)) {
                    continue;
                }

                if ($this->esLineaIGIC($linea)) {
                    $lineasIGIC[] = $linea;
                    continue;
                }

                $lineasExcluidas[] = $linea;
            }

            if (false === empty($lineasIGIC)) {
                $this->acumular($desglose, Calculator::getSubtotals($factura, $lineasIGIC));
            }

            foreach ($lineasExcluidas as $linea) {
                $motivo = empty($linea->excepcioniva) ? 'impuesto' : 'excepcion';
                $codigo = empty($linea->excepcioniva) ? (string) $linea->codimpuesto : (string) $linea->excepcioniva;
                $key = $motivo . '|' . $codigo;
                if (false === isset($excluidas[$key])) {
                    $excluidas[$key] = ['motivo' => $motivo, 'codigo' => $codigo, 'lineas' => 0, 'neto' => 0.0];
                }

                $subtotals = Calculator::getSubtotals($factura, [$linea]);
                $excluidas[$key]['lineas']++;
                $excluidas[$key]['neto'] += (float) $subtotals['neto'];
            }
        }

        usort($desglose, static function (array $a, array $b): int {
            return [$a['iva'], $a['recargo']] <=> [$b['iva'], $b['recargo']];
        });

        foreach ($desglose as $i => $item) {
            $desglose[$i]['neto'] = Tools::round($item['neto']);
            $desglose[$i]['totaliva'] = Tools::round($item['totaliva']);
            $desglose[$i]['totalrecargo'] = Tools::round($item['totalrecargo']);
        }

        ksort($excluidas);
        foreach ($excluidas as $key => $item) {
            $excluidas[$key]['neto'] = Tools::round($item['neto']);
        }

        return ['igic' => $desglose, 'excluidas' => array_values($excluidas)];
    }

    /**
     * Indica si una línea tributa por IGIC según la operación de su impuesto.
     */
    protected function esLineaIGIC($linea): bool
    {
        if (false === empty($linea->excepcioniva) || empty($linea->codimpuesto)) {
            return false;
        }

        $codimpuesto = (string) $linea->codimpuesto;
        if (false === array_key_exists($codimpuesto, $this->impuestos)) {
            $impuesto = new Impuesto();
            $this->impuestos[$codimpuesto] = $impuesto->load($codimpuesto) ? $impuesto : null;
        }

        return $this->impuestos[$codimpuesto] !== null
            && $this->impuestos[$codimpuesto]->operacion === OperacionIVA::ES_OPERATION_03;
    }

    /**
     * Filtro de facturas del período de liquidación y, opcionalmente, de una empresa.
     *
     * Las operaciones se imputan al período en que se devengan: el modelo 420 declara el IGIC
     * devengado en el período y remite a la regla general de devengo del artículo 18 de la
     * Ley 20/1991 (Instrucciones del modelo 420, apdos. 7 y 9). Se usa la fecha de devengo de
     * la factura y, si no la tiene, su fecha, el mismo criterio que usa el núcleo para la fecha
     * del asiento contable de la factura (InvoiceToAccounting).
     */
    public static function wherePeriodo(string $fechaInicio, string $fechaFin, ?int $idempresa): array
    {
        $where = [
            Where::sub([
                Where::isNotNull('fechadevengo'),
                Where::gte('fechadevengo', $fechaInicio),
                Where::lte('fechadevengo', $fechaFin),
            ]),
            Where::orSub([
                Where::isNull('fechadevengo'),
                Where::gte('fecha', $fechaInicio),
                Where::lte('fecha', $fechaFin),
            ]),
        ];
        $where = [Where::sub($where)];
        if (null !== $idempresa) {
            $where[] = Where::eq('idempresa', $idempresa);
        }

        return $where;
    }

    /**
     * @return FacturaCliente[]
     */
    protected function facturasCliente(string $fechaInicio, string $fechaFin, ?int $idempresa): array
    {
        return FacturaCliente::all(static::wherePeriodo($fechaInicio, $fechaFin, $idempresa), ['idfactura' => 'ASC']);
    }

    /**
     * @return FacturaProveedor[]
     */
    protected function facturasProveedor(string $fechaInicio, string $fechaFin, ?int $idempresa): array
    {
        return FacturaProveedor::all(
            static::wherePeriodo($fechaInicio, $fechaFin, $idempresa),
            ['idfactura' => 'ASC']
        );
    }

    private function acumular(array &$desglose, array $subtotals): void
    {
        foreach ($subtotals['iva'] as $item) {
            $key = (float) $item['iva'] . '|' . (float) $item['recargo'];
            if (false === isset($desglose[$key])) {
                $desglose[$key] = [
                    'iva' => (float) $item['iva'],
                    'recargo' => (float) $item['recargo'],
                    'neto' => 0.0,
                    'totaliva' => 0.0,
                    'totalrecargo' => 0.0,
                ];
            }

            $desglose[$key]['neto'] += (float) $item['neto'];
            $desglose[$key]['totaliva'] += (float) $item['totaliva'];
            $desglose[$key]['totalrecargo'] += (float) $item['totalrecargo'];
        }
    }

    /**
     * Calcula el período trimestral por defecto según la fecha actual.
     *
     * Durante el plazo de presentación de un trimestre (Decreto 268/2011, art. 57.6) propone
     * ese trimestre; el resto del tiempo, el trimestre en curso.
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
     * Calcula el total de las cuotas de IGIC devengadas (repercutidas en ventas).
     *
     * Solo suma cuotas de IGIC. No suma recargos: en el IGIC el único recargo es el del régimen
     * especial de comerciantes minoristas, que grava las importaciones y se liquida con el IGIC
     * de la importación (DL 1/2025, art. 70.Uno.a); no tiene casilla en el modelo 420.
     *
     * @param array $desgloseVentas Array del desglose de ventas
     */
    public function calcularTotalDevengado(array $desgloseVentas): float
    {
        return $this->sumar($desgloseVentas, 'totaliva');
    }

    /**
     * Calcula el total de las cuotas de IGIC soportadas en compras.
     *
     * No suma recargos (DL 1/2025, art. 70.Uno.a); ver calcularTotalDevengado().
     *
     * @param array $desgloseCompras Array del desglose de compras
     */
    public function calcularTotalDeducible(array $desgloseCompras): float
    {
        return $this->sumar($desgloseCompras, 'totaliva');
    }

    /**
     * Suma de los recargos que figuran en las facturas del desglose.
     *
     * No entran en el modelo (DL 1/2025, art. 70.Uno.a); se muestran como aviso.
     */
    public function calcularTotalRecargo(array $desglose): float
    {
        return $this->sumar($desglose, 'totalrecargo');
    }

    /**
     * Denominación legal de un tipo de IGIC en la fecha indicada.
     *
     * Desde el 21/10/2025, denominaciones del DL 1/2025, art. 32.1 (el 3 % es «superreducido» y
     * el 5 % «reducido»). El tipo específico del 1 % existe desde el 01/01/2026 (Ley 9/2025,
     * disposición final novena). Para fechas anteriores al 21/10/2025 solo se muestra el
     * porcentaje: el plugin no documenta las denominaciones de la Ley 4/2012.
     *
     * @param float       $tipo  Porcentaje del tipo de IGIC
     * @param string|null $fecha Fecha de devengo (Y-m-d); hoy si es null
     */
    public function nombreTipoIGIC(float $tipo, ?string $fecha = null): string
    {
        $clave = $this->claveTipoIGIC($tipo, $fecha ?? date('Y-m-d'));
        $porcentaje = Tools::number($tipo, $tipo == (int) $tipo ? 0 : 1) . ' %';

        return null === $clave ? $porcentaje : Tools::lang()->trans($clave) . ' (' . $porcentaje . ')';
    }

    /**
     * Indica si el tipo figura en el art. 32.1 del DL 1/2025 vigente en la fecha indicada.
     *
     * Devuelve null para fechas anteriores al 21/10/2025, que el plugin no verifica.
     */
    public function esTipoVigente(float $tipo, string $fecha): ?bool
    {
        if (static::fecha($fecha) < self::FECHA_TEXTO_REFUNDIDO) {
            return null;
        }

        return $this->claveTipoIGIC($tipo, $fecha) !== null;
    }

    /**
     * Plazo de presentación del Modelo 420 de un trimestre.
     *
     * Decreto 268/2011, art. 57.6: los trimestres se presentan durante los veinte primeros días
     * naturales del mes siguiente, salvo el último del año, que se presenta durante el mes de
     * enero del año siguiente. El plazo se amplía al siguiente día hábil si termina en día
     * inhábil (ficha del modelo 420 de la ATC); esa ampliación no se calcula aquí.
     *
     * @return array{desde: string, hasta: string}
     */
    public function plazoPresentacion(string $periodo, int $anyo): array
    {
        return match ($periodo) {
            'T1' => ['desde' => $anyo . '-04-01', 'hasta' => $anyo . '-04-20'],
            'T2' => ['desde' => $anyo . '-07-01', 'hasta' => $anyo . '-07-20'],
            'T3' => ['desde' => $anyo . '-10-01', 'hasta' => $anyo . '-10-20'],
            // el 425 se presenta junto con el 4T (Decreto 268/2011, art. 57.8)
            'T4', 'ANUAL' => ['desde' => ($anyo + 1) . '-01-01', 'hasta' => ($anyo + 1) . '-01-31'],
            default => throw new \InvalidArgumentException('Periodo no válido: ' . $periodo),
        };
    }

    /**
     * Clave de traducción de la denominación del tipo, o null si el tipo no tiene denominación
     * legal verificada en esa fecha.
     */
    protected function claveTipoIGIC(float $tipo, string $fecha): ?string
    {
        $fecha = static::fecha($fecha);
        if ($fecha < self::FECHA_TEXTO_REFUNDIDO) {
            return null;
        }

        return match (true) {
            $tipo == 0 => 'igic-tipo-cero',
            $tipo == 1 && $fecha >= self::FECHA_TIPO_ESPECIFICO => 'igic-tipo-especifico',
            $tipo == 3 => 'igic-tipo-superreducido',
            $tipo == 5 => 'igic-tipo-reducido',
            $tipo == 7 => 'igic-tipo-general',
            $tipo == 9.5, $tipo == 15 => 'igic-tipo-incrementado',
            $tipo == 20 => 'igic-tipo-especial',
            default => null,
        };
    }

    protected static function fecha(string $fecha): string
    {
        return date('Y-m-d', strtotime($fecha));
    }

    private function sumar(array $desglose, string $campo): float
    {
        $total = 0.0;
        foreach ($desglose as $item) {
            $total += (float) $item[$campo];
        }

        return Tools::round($total);
    }
}
