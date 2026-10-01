<?php

/**
 * This file is part of ModelosIGIC plugin for FacturaScripts.
 * Copyright (C) 2016-2026 Carlos Garcia Gomez <neorazorx@gmail.com>
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

namespace FacturaScripts\Plugins\ModelosIGIC\Controller;

use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Plugins\ModelosIGIC\Lib\CasillasModelo425;
use FacturaScripts\Plugins\ModelosIGIC\Lib\ComparativaIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGICFactura;

/**
 * Controlador para el Modelo 425 - Declaración-resumen anual del IGIC.
 *
 * La declaración-resumen anual se presenta conjuntamente con la autoliquidación del
 * último período del año (Decreto 268/2011, art. 57.8), es decir, durante el mes de
 * enero del año siguiente (art. 57.6). No la presentan quienes llevan los libros
 * registro por el SII (art. 57.8 en relación con el art. 49.5).
 *
 * @see https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-425
 */
class Modelo425 extends Controller
{
    /** @var Ejercicio */
    public Ejercicio $ejercicio;

    /** @var IGICHelper */
    protected IGICHelper $helper;

    /** @var bool */
    public bool $allowUpdate = false;

    /** @var ?Ejercicio */
    public ?Ejercicio $selectedEjercicio = null;

    /** @var ?array */
    private ?array $casillas = null;

    /** @var ?array Análisis de las compras del período, calculado una sola vez por carga */
    private ?array $analisisCompras = null;

    /** @var ?array Análisis de las ventas del período, calculado una sola vez por carga */
    private ?array $analisisVentas = null;

    /** @var ?DeclaracionIGIC */
    public ?DeclaracionIGIC $declaracion = null;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'modelo-425';
        $data['icon'] = 'fa-solid fa-file-invoice-dollar';
        $data['showonmenu'] = true;
        return $data;
    }

    public function run(): void
    {
        parent::run();

        $this->allowUpdate = (bool) $this->permissions->allowUpdate;
        $this->helper = $this->nuevoHelper();
        $this->ejercicio = new Ejercicio();

        // ejercicio seleccionado o el de la fecha actual
        $codEjercicio = $this->request()->inputOrQuery('codejercicio', '');
        $this->selectedEjercicio = empty($codEjercicio) ?
            $this->getEjercicioByFecha(Tools::date()) :
            $this->getEjercicio($codEjercicio);

        if ($this->selectedEjercicio) {
            $this->declaracion = $this->getDeclaracionIGICPorEjercicio($this->selectedEjercicio->codejercicio);
        }

        $this->execAction($this->request()->input('proceso', ''));
        $this->view('Modelo425.html.twig');
    }

    /**
     * Ejecuta la acción solicitada.
     */
    /**
     * Returns the normalized year range requested for the comparison.
     */
    protected function rangoComparativa(): array
    {
        $actual = $this->selectedEjercicio
            ? (int) date('Y', strtotime((string) $this->selectedEjercicio->fechainicio))
            : (int) date('Y');
        $desde = (int) $this->request()->inputOrQuery('comparar_desde', $actual - 4);
        $hasta = (int) $this->request()->inputOrQuery('comparar_hasta', $actual);

        return $desde <= $hasta ? [$desde, $hasta] : [$hasta, $desde];
    }

    protected function execAction(string $action): void
    {
        $acciones = ['guardar', 'marcar-presentado'];
        if (false === in_array($action, $acciones, true) || false === $this->validateFormToken()) {
            return;
        }

        if (false === $this->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return;
        }

        if ($action === 'guardar' && $this->selectedEjercicio) {
            $this->guardarModelo425();
        } elseif ($action === 'marcar-presentado' && $this->declaracion) {
            $this->marcarPresentado();
        }
    }

    /**
     * Marca el modelo fiscal como presentado.
     */
    protected function marcarPresentado(): void
    {
        $numeroReferencia = $this->request()->input('numeroreferencia', '');
        $fechaPresentacion = $this->request()->input('fechapresentacion') ?: Tools::date();

        if ($this->declaracion->marcarPresentado($numeroReferencia ?: null, $fechaPresentacion)) {
            Tools::log()->notice('modelo-marcado-presentado');
        } else {
            Tools::log()->error('error-marcar-presentado');
        }
    }

    /**
     * Obtiene todos los ejercicios disponibles.
     */
    public function allEjercicios(): array
    {
        return Ejercicio::all(
            [Where::eq('idempresa', $this->empresa->idempresa)],
            ['fechainicio' => 'DESC'],
            0,
            50
        );
    }

    /**
     * Returns the historical Model 425 comparison for the current company.
     */
    public function comparativaHistorica(): array
    {
        [$desde, $hasta] = $this->rangoComparativa();
        $declaraciones = array_filter(
            DeclaracionIGIC::all([Where::eq('tipo', '425')], ['fechainicio' => 'ASC']),
            fn (DeclaracionIGIC $declaracion): bool => $declaracion->getIdEmpresa() === (int) $this->empresa->idempresa
        );

        return ComparativaIGIC::modelo425($declaraciones, $desde, $hasta);
    }

    public function comparativaDesde(): int
    {
        return $this->rangoComparativa()[0];
    }

    public function comparativaHasta(): int
    {
        return $this->rangoComparativa()[1];
    }

    /**
     * Casillas del Modelo 425 del ejercicio seleccionado.
     */
    public function casillas(): array
    {
        if (null === $this->casillas && $this->selectedEjercicio !== null) {
            $this->casillas = (new CasillasModelo425($this->helper))->calcular(
                $this->desgloseIGICVentas(),
                $this->desgloseIGICCompras(),
                $this->selectedEjercicio->fechafin,
                $this->getModelos420()
            );
        }

        return $this->casillas ?? [];
    }

    /**
     * Líneas de compra del ejercicio que no entran en el cálculo.
     */
    public function excluidasCompras(): array
    {
        return $this->analisisCompras()['excluidas'];
    }

    /**
     * Líneas de venta del ejercicio que no entran en el cálculo.
     */
    public function excluidasVentas(): array
    {
        return $this->analisisVentas()['excluidas'];
    }

    /**
     * Plazo de presentación del resumen anual (Decreto 268/2011, arts. 57.6 y 57.8).
     */
    public function plazo(): array
    {
        if ($this->selectedEjercicio === null) {
            return [];
        }

        return $this->helper->plazoPresentacion(
            'ANUAL',
            (int) date('Y', strtotime($this->selectedEjercicio->fechafin))
        );
    }

    /**
     * Obtiene el desglose de IGIC de las compras para el ejercicio seleccionado.
     */
    public function desgloseIGICCompras(): array
    {
        return $this->analisisCompras()['igic'];
    }

    /**
     * Obtiene el desglose de IGIC de las ventas para el ejercicio seleccionado.
     */
    public function desgloseIGICVentas(): array
    {
        return $this->analisisVentas()['igic'];
    }

    /**
     * Calcula el total del IGIC devengado en el ejercicio.
     */
    public function totalDevengado(): float
    {
        return $this->helper->calcularTotalDevengado($this->desgloseIGICVentas());
    }

    /**
     * Calcula el total del IGIC deducible en el ejercicio.
     */
    public function totalDeducible(): float
    {
        return $this->helper->calcularTotalDeducible($this->desgloseIGICCompras());
    }

    /**
     * Calcula el resultado anual (devengado - deducible).
     */
    public function resultado(): float
    {
        return $this->totalDevengado() - $this->totalDeducible();
    }

    /**
     * Obtiene el total de la base imponible de ventas.
     */
    public function totalBaseVentas(): float
    {
        $total = 0.0;
        foreach ($this->desgloseIGICVentas() as $item) {
            $total += $item['neto'];
        }
        return $total;
    }

    /**
     * Obtiene el total de la base imponible de compras.
     */
    public function totalBaseCompras(): float
    {
        $total = 0.0;
        foreach ($this->desgloseIGICCompras() as $item) {
            $total += $item['neto'];
        }
        return $total;
    }

    /**
     * Guarda el modelo 425 y las facturas asociadas en una transacción.
     */
    protected function guardarModelo425(): void
    {
        if ($this->declaracion !== null) {
            Tools::log()->warning('modelo-425-ya-existe');
            return;
        }

        $modelo = $this->nuevaDeclaracion();
        $modelo->tipo = '425';
        $modelo->periodo = 'ANUAL';
        $modelo->codejercicio = $this->selectedEjercicio->codejercicio;
        $modelo->fechainicio = $this->selectedEjercicio->fechainicio;
        $modelo->fechafin = $this->selectedEjercicio->fechafin;
        $modelo->totaldevengado = $this->totalDevengado();
        $modelo->totaldeducible = $this->totalDeducible();
        $modelo->resultado = $modelo->totaldevengado - $modelo->totaldeducible;
        $modelo->estado = 'borrador';

        // las tablas no se pueden crear dentro de una transacción
        new DeclaracionIGICFactura();

        $db = $this->db();
        $newTransaction = false === $db->inTransaction() && $db->beginTransaction();
        if ($modelo->save() && $modelo->guardarFacturas((int) $this->selectedEjercicio->idempresa)) {
            if ($newTransaction) {
                $db->commit();
            }
            $this->declaracion = $modelo;
            Tools::log()->notice('modelo-425-guardado');
            return;
        }

        if ($newTransaction) {
            $db->rollback();
        }
        Tools::log()->error('error-guardar-modelo-425');
    }

    protected function nuevaDeclaracion(): DeclaracionIGIC
    {
        return new DeclaracionIGIC();
    }

    /**
     * Obtiene el modelo fiscal 425 para un ejercicio.
     */
    protected function getDeclaracionIGICPorEjercicio(string $codejercicio): ?DeclaracionIGIC
    {
        return DeclaracionIGIC::findWhere([
            Where::eq('tipo', '425'),
            Where::eq('codejercicio', $codejercicio),
        ], ['idmodelo' => 'DESC']);
    }

    /**
     * Obtiene las facturas de cliente del modelo fiscal.
     */
    public function getFacturasClienteModelo(): array
    {
        return $this->declaracion ? $this->declaracion->getFacturasCliente() : [];
    }

    /**
     * Obtiene las facturas de proveedor del modelo fiscal.
     */
    public function getFacturasProveedorModelo(): array
    {
        return $this->declaracion ? $this->declaracion->getFacturasProveedor() : [];
    }

    /**
     * Obtiene los modelos 420 trimestrales del ejercicio.
     */
    public function getModelos420(): array
    {
        if ($this->selectedEjercicio === null) {
            return [];
        }

        return DeclaracionIGIC::all([
            Where::eq('tipo', '420'),
            Where::eq('codejercicio', $this->selectedEjercicio->codejercicio),
        ], ['periodo' => 'ASC', 'idmodelo' => 'ASC']);
    }

    /**
     * Obtiene un ejercicio de la empresa por su código.
     */
    protected function getEjercicio(string $codejercicio): ?Ejercicio
    {
        $ejercicio = new Ejercicio();
        if (false === $ejercicio->load($codejercicio)) {
            return null;
        }

        return $ejercicio->idempresa == $this->empresa->idempresa ? $ejercicio : null;
    }

    /**
     * Obtiene el ejercicio de la empresa que contiene la fecha indicada.
     */
    protected function getEjercicioByFecha(string $fecha): ?Ejercicio
    {
        $ejercicio = new Ejercicio();
        $ejercicio->idempresa = $this->empresa->idempresa;
        return $ejercicio->loadFromDate($fecha, false, false) ? $ejercicio : null;
    }

    protected function nuevoHelper(): IGICHelper
    {
        return new IGICHelper();
    }

    private function analisisCompras(): array
    {
        if (null === $this->analisisCompras) {
            $this->analisisCompras = $this->selectedEjercicio === null ? ['igic' => [], 'excluidas' => []] :
                $this->helper->analisisCompras(
                    $this->selectedEjercicio->fechainicio,
                    $this->selectedEjercicio->fechafin,
                    (int) $this->selectedEjercicio->idempresa
                );
        }

        return $this->analisisCompras;
    }

    private function analisisVentas(): array
    {
        if (null === $this->analisisVentas) {
            $this->analisisVentas = $this->selectedEjercicio === null ? ['igic' => [], 'excluidas' => []] :
                $this->helper->analisisVentas(
                    $this->selectedEjercicio->fechainicio,
                    $this->selectedEjercicio->fechafin,
                    (int) $this->selectedEjercicio->idempresa
                );
        }

        return $this->analisisVentas;
    }
}
