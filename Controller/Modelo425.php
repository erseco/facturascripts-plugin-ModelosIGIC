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
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGICFactura;

/**
 * Controlador para el Modelo 425 - Declaración-resumen anual del IGIC.
 *
 * El Modelo 425 es la declaración-resumen anual del Impuesto General Indirecto
 * Canario (IGIC) que deben presentar los empresarios y profesionales durante
 * el mes de enero del año siguiente al que se refiera la declaración.
 *
 * Este modelo resume todas las operaciones del ejercicio y debe coincidir con
 * la suma de los cuatro modelos 420 trimestrales presentados durante el año.
 *
 * Plazo de presentación: del 1 al 30 de enero del año siguiente
 *
 * @see https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-425
 */
class Modelo425 extends Controller
{
    /** @var Ejercicio */
    public Ejercicio $ejercicio;

    /** @var IGICHelper */
    protected IGICHelper $helper;

    /** @var ?Ejercicio */
    public ?Ejercicio $selectedEjercicio = null;

    /** @var array */
    private array $desgloseCompras = [];

    /** @var array */
    private array $desgloseVentas = [];

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

        $this->helper = new IGICHelper();
        $this->ejercicio = new Ejercicio();

        // ejercicio seleccionado o el de la fecha actual
        $codEjercicio = $this->request()->inputOrQuery('codejercicio', '');
        $this->selectedEjercicio = empty($codEjercicio) ?
            $this->getEjercicioByFecha(Tools::date()) :
            $this->getEjercicio($codEjercicio);

        if ($this->selectedEjercicio) {
            $this->declaracion = $this->getDeclaracionIGICPorEjercicio($this->selectedEjercicio->codejercicio);
        }

        $action = $this->request()->input('proceso', '');
        if (in_array($action, ['guardar', 'marcar-presentado'], true) && $this->validateFormToken()) {
            if ($action === 'guardar' && $this->selectedEjercicio) {
                $this->guardarModelo425();
            } elseif ($action === 'marcar-presentado' && $this->declaracion) {
                $this->marcarPresentado();
            }
        }

        $this->view('Modelo425.html.twig');
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
     * Obtiene el desglose de IGIC de las compras para el ejercicio seleccionado.
     */
    public function desgloseIGICCompras(): array
    {
        if (empty($this->desgloseCompras) && $this->selectedEjercicio !== null) {
            $this->desgloseCompras = $this->helper->desgloseIGICCompras(
                $this->selectedEjercicio->fechainicio,
                $this->selectedEjercicio->fechafin,
                (int) $this->selectedEjercicio->idempresa
            );
        }
        return $this->desgloseCompras;
    }

    /**
     * Obtiene el desglose de IGIC de las ventas para el ejercicio seleccionado.
     */
    public function desgloseIGICVentas(): array
    {
        if (empty($this->desgloseVentas) && $this->selectedEjercicio !== null) {
            $this->desgloseVentas = $this->helper->desgloseIGICVentas(
                $this->selectedEjercicio->fechainicio,
                $this->selectedEjercicio->fechafin,
                (int) $this->selectedEjercicio->idempresa
            );
        }
        return $this->desgloseVentas;
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

        $modelo = new DeclaracionIGIC();
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
}
