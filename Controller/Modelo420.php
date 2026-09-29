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

namespace FacturaScripts\Plugins\ModelosIGIC\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGICFactura;

/**
 * Controlador para el Modelo 420 - Autoliquidación trimestral del IGIC.
 *
 * El Modelo 420 es el formulario de autoliquidación del Impuesto General Indirecto
 * Canario (IGIC) que deben presentar los empresarios y profesionales que realicen
 * operaciones en el régimen ordinario en las Islas Canarias.
 *
 * Plazos de presentación:
 * - T1 (enero-marzo): del 1 al 20 de abril
 * - T2 (abril-junio): del 1 al 20 de julio
 * - T3 (julio-septiembre): del 1 al 20 de octubre
 * - T4 (octubre-diciembre): del 1 al 30 de enero del año siguiente
 *
 * @see https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-420
 */
class Modelo420 extends Controller
{
    /** @var bool */
    public bool $allowDelete = false;

    /** @var array */
    public array $auxRegiva = [];

    /** @var string */
    public string $fechaDesde = '';

    /** @var string */
    public string $fechaHasta = '';

    /** @var IGICHelper */
    protected IGICHelper $helper;

    /** @var string */
    public string $periodo = '';

    /** @var RegularizacionImpuesto */
    public RegularizacionImpuesto $regiva;

    /** @var ?RegularizacionImpuesto */
    public ?RegularizacionImpuesto $selectedRegiva = null;

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
        $data['title'] = 'modelo-420';
        $data['icon'] = 'fa-solid fa-file-invoice';
        $data['showonmenu'] = true;
        return $data;
    }

    public function run(): void
    {
        parent::run();

        $this->allowDelete = $this->permissions->allowDelete;
        $this->helper = new IGICHelper();
        $this->regiva = new RegularizacionImpuesto();

        // Calcular período por defecto
        $periodoDefault = $this->helper->calcularPeriodoActual();
        $this->fechaDesde = $periodoDefault['fecha_desde'];
        $this->fechaHasta = $periodoDefault['fecha_hasta'];
        $this->periodo = $periodoDefault['periodo'];

        // Procesar fechas del formulario
        if ($this->request()->request->has('desde')) {
            $this->fechaDesde = $this->request()->request->get('desde');
        }
        if ($this->request()->request->has('hasta')) {
            $this->fechaHasta = $this->request()->request->get('hasta');
        }

        // Ver regularización existente
        $id = $this->request()->query->getInt('id');
        if ($id > 0) {
            $this->selectedRegiva = $this->regiva->get($id);
            // Buscar modelo fiscal asociado
            if ($this->selectedRegiva) {
                $this->declaracion = $this->getDeclaracionIGICPorRegiva($this->selectedRegiva->idregiva);
            }
        }

        // Procesar acciones
        $action = $this->request()->request->get('proceso', '');
        if ($action === 'comprobar') {
            $this->completarRegiva();
        } elseif ($action === 'guardar') {
            $this->guardarRegiva();
        } elseif ($action === 'marcar-presentado' && $this->declaracion) {
            $this->marcarPresentado();
        } elseif ($action === 'crear-rectificativo' && $this->declaracion) {
            $this->crearRectificativo();
        }

        // Descargar fichero ATC
        $downloadATC = $this->request()->query->get('download-atc', '');
        if ($downloadATC === '1' && $this->declaracion) {
            $this->descargarATC();
            return;
        }

        // Eliminar regularización
        $deleteId = $this->request()->query->getInt('delete');
        if ($deleteId > 0 && $this->allowDelete) {
            $this->eliminarRegiva($deleteId);
        }

        $this->view('Modelo420.html.twig');
    }

    /**
     * Obtiene el desglose de IGIC de las compras para la regularización seleccionada.
     */
    public function desgloseIGICCompras(): array
    {
        if (empty($this->desgloseCompras) && $this->selectedRegiva !== null) {
            $this->desgloseCompras = $this->helper->desgloseIGICCompras(
                $this->selectedRegiva->fechainicio,
                $this->selectedRegiva->fechafin
            );
        }
        return $this->desgloseCompras;
    }

    /**
     * Obtiene el desglose de IGIC de las ventas para la regularización seleccionada.
     */
    public function desgloseIGICVentas(): array
    {
        if (empty($this->desgloseVentas) && $this->selectedRegiva !== null) {
            $this->desgloseVentas = $this->helper->desgloseIGICVentas(
                $this->selectedRegiva->fechainicio,
                $this->selectedRegiva->fechafin
            );
        }
        return $this->desgloseVentas;
    }

    /**
     * Calcula el total del IGIC devengado.
     */
    public function totalDevengado(): float
    {
        return $this->helper->calcularTotalDevengado($this->desgloseIGICVentas());
    }

    /**
     * Calcula el total del IGIC deducible.
     */
    public function totalDeducible(): float
    {
        return $this->helper->calcularTotalDeducible($this->desgloseIGICCompras());
    }

    /**
     * Obtiene todas las regularizaciones existentes.
     */
    public function allRegularizaciones(): array
    {
        return $this->regiva->all([], ['fechainicio' => 'DESC'], 0, 50);
    }

    /**
     * Calcula la previsualización del asiento de regularización.
     */
    protected function completarRegiva(): void
    {
        // Verificar facturas sin asiento
        if ($this->helper->hayFacturasSinAsiento($this->fechaDesde, $this->fechaHasta)) {
            Tools::log()->error('facturas-sin-asiento');
            return;
        }

        // Obtener ejercicio
        $eje = $this->getEjercicioByFecha($this->fechaDesde, true);
        if (false === $eje) {
            Tools::log()->error('ejercicio-cerrado');
            return;
        }

        // Calcular las partidas propuestas
        $this->auxRegiva = $this->helper->calcularRegularizacion(
            $this->fechaDesde,
            $this->fechaHasta,
            $eje->codejercicio
        );

        if (empty($this->auxRegiva)) {
            Tools::log()->warning('sin-datos-regularizacion');
        }
    }

    /**
     * Guarda la regularización creando el asiento contable.
     */
    protected function guardarRegiva(): void
    {
        $eje = $this->getEjercicioByFecha($this->fechaDesde, true);
        if (false === $eje) {
            Tools::log()->error('ejercicio-cerrado');
            return;
        }

        $periodo = $this->request()->request->get('periodo', $this->periodo);
        $saldo = 0.0;

        // Crear asiento
        $asiento = new Asiento();
        $asiento->codejercicio = $eje->codejercicio;
        $asiento->concepto = 'REGULARIZACIÓN IGIC ' . $periodo;
        $asiento->fecha = $this->fechaHasta;
        $asiento->editable = false;

        if (false === $asiento->save()) {
            Tools::log()->error('error-guardar-asiento');
            return;
        }

        $continuar = true;

        // Partidas del IGIC soportado
        foreach ($this->helper->getSubcuentasEspeciales('IVASOP', $eje->codejercicio) as $sctaIGICSop) {
            $partida = new Partida();
            $partida->idasiento = $asiento->idasiento;
            $partida->concepto = $asiento->concepto;
            $partida->coddivisa = $sctaIGICSop->coddivisa;
            $partida->codsubcuenta = $sctaIGICSop->codsubcuenta;
            $partida->idsubcuenta = $sctaIGICSop->idsubcuenta;

            $totales = $this->helper->getTotalesSubcuenta(
                $sctaIGICSop->idsubcuenta,
                $this->fechaDesde,
                $this->fechaHasta
            );

            if ($totales['saldo'] != 0) {
                $partida->debe = $totales['haber'];
                $partida->haber = $totales['debe'];
                $saldo += $totales['haber'] - $totales['debe'];

                if (false === $partida->save()) {
                    Tools::log()->error('error-guardar-partida-igic-soportado');
                    $continuar = false;
                }
            }
        }

        // Partidas del IGIC repercutido
        foreach ($this->helper->getSubcuentasEspeciales('IVAREP', $eje->codejercicio) as $sctaIGICRep) {
            $partida = new Partida();
            $partida->idasiento = $asiento->idasiento;
            $partida->concepto = $asiento->concepto;
            $partida->coddivisa = $sctaIGICRep->coddivisa;
            $partida->codsubcuenta = $sctaIGICRep->codsubcuenta;
            $partida->idsubcuenta = $sctaIGICRep->idsubcuenta;

            $totales = $this->helper->getTotalesSubcuenta(
                $sctaIGICRep->idsubcuenta,
                $this->fechaDesde,
                $this->fechaHasta
            );

            if ($totales['saldo'] != 0) {
                $partida->debe = $totales['haber'];
                $partida->haber = $totales['debe'];
                $saldo += $totales['haber'] - $totales['debe'];

                if (false === $partida->save()) {
                    Tools::log()->error('error-guardar-partida-igic-repercutido');
                    $continuar = false;
                }
            }
        }

        if ($continuar) {
            // Partida de cierre (acreedor o deudor)
            if ($saldo > 0) {
                $sctaAcr = $this->helper->getSubcuentaEspecial('IVAACR', $eje->codejercicio);
                if ($sctaAcr) {
                    $partida = new Partida();
                    $partida->idasiento = $asiento->idasiento;
                    $partida->concepto = $asiento->concepto;
                    $partida->coddivisa = $sctaAcr->coddivisa;
                    $partida->codsubcuenta = $sctaAcr->codsubcuenta;
                    $partida->idsubcuenta = $sctaAcr->idsubcuenta;
                    $partida->debe = 0;
                    $partida->haber = $saldo;

                    if (false === $partida->save()) {
                        Tools::log()->error('error-guardar-partida-igic-acreedor');
                        $continuar = false;
                    }
                } else {
                    Tools::log()->error('subcuenta-acreedora-no-encontrada');
                    $continuar = false;
                }
            } elseif ($saldo < 0) {
                $sctaDeu = $this->helper->getSubcuentaEspecial('IVADEU', $eje->codejercicio);
                if ($sctaDeu) {
                    $partida = new Partida();
                    $partida->idasiento = $asiento->idasiento;
                    $partida->concepto = $asiento->concepto;
                    $partida->coddivisa = $sctaDeu->coddivisa;
                    $partida->codsubcuenta = $sctaDeu->codsubcuenta;
                    $partida->idsubcuenta = $sctaDeu->idsubcuenta;
                    $partida->debe = abs($saldo);
                    $partida->haber = 0;

                    if (false === $partida->save()) {
                        Tools::log()->error('error-guardar-partida-igic-deudor');
                        $continuar = false;
                    }
                } else {
                    Tools::log()->error('subcuenta-deudora-no-encontrada');
                    $continuar = false;
                }
            }
        }

        if ($continuar) {
            // Recalcular importe del asiento
            $asiento->fix();

            // Guardar la regularización
            $regiva = new RegularizacionImpuesto();
            $regiva->codejercicio = $eje->codejercicio;
            $regiva->fechaasiento = $asiento->fecha;
            $regiva->fechafin = $this->fechaHasta;
            $regiva->fechainicio = $this->fechaDesde;
            $regiva->idasiento = $asiento->idasiento;
            $regiva->periodo = $periodo;

            if ($regiva->save()) {
                // Guardar modelo fiscal y facturas
                $this->guardarDeclaracionIGIC($regiva, $eje->codejercicio, $periodo);
                Tools::log()->notice('regularizacion-guardada', ['%url%' => $regiva->url()]);
            } else {
                $asiento->delete();
                Tools::log()->error('error-guardar-regularizacion');
            }
        } else {
            $asiento->delete();
        }
    }

    /**
     * Guarda la declaración IGIC y las facturas asociadas.
     */
    protected function guardarDeclaracionIGIC(
        RegularizacionImpuesto $regiva,
        string $codejercicio,
        string $periodo
    ): void {
        $modelo = new DeclaracionIGIC();
        $modelo->tipo = '420';
        $modelo->periodo = $periodo;
        $modelo->codejercicio = $codejercicio;
        $modelo->fechainicio = $regiva->fechainicio;
        $modelo->fechafin = $regiva->fechafin;
        $modelo->idregiva = $regiva->idregiva;
        $modelo->totaldevengado = $this->totalDevengado();
        $modelo->totaldeducible = $this->totalDeducible();
        $modelo->resultado = $modelo->totaldevengado - $modelo->totaldeducible;
        $modelo->estado = 'borrador';

        if ($modelo->save()) {
            $this->guardarFacturasModelo($modelo);
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
     * Obtiene el modelo fiscal asociado a una regularización.
     */
    protected function getDeclaracionIGICPorRegiva(int $idregiva): ?DeclaracionIGIC
    {
        $modelo = new DeclaracionIGIC();
        $where = [new DataBaseWhere('idregiva', $idregiva)];
        $modelos = $modelo->all($where, [], 0, 1);
        return empty($modelos) ? null : $modelos[0];
    }

    /**
     * Obtiene las facturas de cliente del modelo fiscal seleccionado.
     */
    public function getFacturasClienteModelo(): array
    {
        return $this->declaracion ? $this->declaracion->getFacturasCliente() : [];
    }

    /**
     * Obtiene las facturas de proveedor del modelo fiscal seleccionado.
     */
    public function getFacturasProveedorModelo(): array
    {
        return $this->declaracion ? $this->declaracion->getFacturasProveedor() : [];
    }

    /**
     * Elimina una regularización.
     */
    protected function eliminarRegiva(int $id): void
    {
        $regiva = $this->regiva->get($id);
        if ($regiva && $regiva->delete()) {
            Tools::log()->notice('regularizacion-eliminada');
        } else {
            Tools::log()->error('error-eliminar-regularizacion');
        }
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
     * Crea un modelo rectificativo.
     */
    protected function crearRectificativo(): void
    {
        $nuevoModelo = $this->declaracion->crearRectificativo();
        if ($nuevoModelo) {
            $this->declaracion = $nuevoModelo;
            Tools::log()->notice('modelo-rectificativo-creado');
        } else {
            Tools::log()->error('error-crear-rectificativo');
        }
    }

    /**
     * Genera y descarga el fichero ATC para presentación telemática.
     */
    protected function descargarATC(): void
    {
        $generator = new ATCFileGenerator($this->declaracion);

        // Obtener el desglose de IGIC
        $desgloseVentas = $this->helper->desgloseIGICVentas(
            $this->declaracion->fechainicio,
            $this->declaracion->fechafin
        );
        $desgloseCompras = $this->helper->desgloseIGICCompras(
            $this->declaracion->fechainicio,
            $this->declaracion->fechafin
        );

        $generator->setDesgloseVentas($desgloseVentas)
            ->setDesgloseCompras($desgloseCompras);

        $filename = $generator->getFilename();
        $content = $generator->generate();

        // Enviar cabeceras HTTP para descarga
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo $content;
        exit;
    }

    /**
     * Obtiene el ejercicio que contiene la fecha indicada.
     *
     * @param string $fecha      Fecha a buscar
     * @param bool   $onlyOpened Solo ejercicios abiertos
     *
     * @return Ejercicio|false
     */
    protected function getEjercicioByFecha(string $fecha, bool $onlyOpened = false)
    {
        $ejercicio = new Ejercicio();
        $where = [
            new DataBaseWhere('fechainicio', $fecha, '<='),
            new DataBaseWhere('fechafin', $fecha, '>='),
        ];

        if ($onlyOpened) {
            $where[] = new DataBaseWhere('estado', 'ABIERTO');
        }

        $ejercicios = $ejercicio->all($where, [], 0, 1);
        return empty($ejercicios) ? false : $ejercicios[0];
    }
}
