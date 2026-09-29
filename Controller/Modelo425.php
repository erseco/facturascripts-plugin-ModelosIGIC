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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
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

        // Obtener ejercicio seleccionado o el actual
        $codEjercicio = $this->request()->request->get(
            'codejercicio',
            $this->request()->query->get('codejercicio', '')
        );

        if (empty($codEjercicio)) {
            $this->selectedEjercicio = $this->getEjercicioByFecha(date('Y-m-d'));
        } else {
            $this->selectedEjercicio = $this->ejercicio->get($codEjercicio);
        }

        // Buscar modelo fiscal 425 existente para este ejercicio
        if ($this->selectedEjercicio) {
            $this->declaracion = $this->getDeclaracionIGICPorEjercicio($this->selectedEjercicio->codejercicio);
        }

        // Procesar acciones
        $action = $this->request()->request->get('proceso', '');
        if ($action === 'guardar' && $this->selectedEjercicio) {
            $this->guardarModelo425();
        } elseif ($action === 'marcar-presentado' && $this->declaracion) {
            $this->marcarPresentado();
        }

        $this->view('Modelo425.html.twig');
    }

    /**
     * Marca el modelo fiscal como presentado.
     */
    protected function marcarPresentado(): void
    {
        $numeroReferencia = $this->request()->request->get('numeroreferencia', '');
        $fechaPresentacion = $this->request()->request->get('fechapresentacion', date('Y-m-d'));

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
        return $this->ejercicio->all([], ['codejercicio' => 'DESC'], 0, 50);
    }

    /**
     * Obtiene el desglose de IGIC de las compras para el ejercicio seleccionado.
     */
    public function desgloseIGICCompras(): array
    {
        if (empty($this->desgloseCompras) && $this->selectedEjercicio !== null) {
            $this->desgloseCompras = $this->helper->desgloseIGICCompras(
                $this->selectedEjercicio->fechainicio,
                $this->selectedEjercicio->fechafin
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
                $this->selectedEjercicio->fechafin
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
     * Guarda el modelo 425 y las facturas asociadas.
     */
    protected function guardarModelo425(): void
    {
        // Verificar si ya existe un modelo 425 para este ejercicio
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

        if ($modelo->save()) {
            $this->guardarFacturasModelo($modelo);
            $this->declaracion = $modelo;
            Tools::log()->notice('modelo-425-guardado');
        } else {
            Tools::log()->error('error-guardar-modelo-425');
        }
    }

    /**
     * Guarda las facturas incluidas en el modelo fiscal.
     */
    protected function guardarFacturasModelo(DeclaracionIGIC $modelo): void
    {
        // Facturas de cliente (ventas - IGIC devengado)
        $facturaCliente = new FacturaCliente();
        $where = [
            new DataBaseWhere('fecha', $modelo->fechainicio, '>='),
            new DataBaseWhere('fecha', $modelo->fechafin, '<='),
        ];
        foreach ($facturaCliente->all($where) as $factura) {
            $mf = DeclaracionIGICFactura::fromFacturaCliente($factura, $modelo->idmodelo);
            $mf->save();
        }

        // Facturas de proveedor (compras - IGIC deducible)
        $facturaProveedor = new FacturaProveedor();
        foreach ($facturaProveedor->all($where) as $factura) {
            $mf = DeclaracionIGICFactura::fromFacturaProveedor($factura, $modelo->idmodelo);
            $mf->save();
        }
    }

    /**
     * Obtiene el modelo fiscal 425 para un ejercicio.
     */
    protected function getDeclaracionIGICPorEjercicio(string $codejercicio): ?DeclaracionIGIC
    {
        $modelo = new DeclaracionIGIC();
        $where = [
            new DataBaseWhere('tipo', '425'),
            new DataBaseWhere('codejercicio', $codejercicio),
        ];
        $modelos = $modelo->all($where, [], 0, 1);
        return empty($modelos) ? null : $modelos[0];
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

        $modelo = new DeclaracionIGIC();
        $where = [
            new DataBaseWhere('tipo', '420'),
            new DataBaseWhere('codejercicio', $this->selectedEjercicio->codejercicio),
        ];
        return $modelo->all($where, ['periodo' => 'ASC']);
    }

    /**
     * Obtiene el ejercicio que contiene la fecha indicada.
     */
    protected function getEjercicioByFecha(string $fecha): ?Ejercicio
    {
        $where = [
            new DataBaseWhere('fechainicio', $fecha, '<='),
            new DataBaseWhere('fechafin', $fecha, '>='),
        ];
        $ejercicios = $this->ejercicio->all($where, [], 0, 1);
        return empty($ejercicios) ? null : $ejercicios[0];
    }
}
