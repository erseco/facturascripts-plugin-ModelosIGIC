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

use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
use FacturaScripts\Plugins\ModelosIGIC\Lib\CasillasModelo420;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use FacturaScripts\Plugins\ModelosIGIC\Lib\RegularizacionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;

/**
 * Controlador para el Modelo 420 - Autoliquidación trimestral del IGIC.
 *
 * Calcula la regularización del IGIC de un trimestre, genera su asiento contable,
 * registra la declaración y permite descargar el fichero para la ATC.
 *
 * El periodo de liquidación del 420 es siempre el trimestre natural (Decreto 268/2011,
 * art. 57.5). Quien liquida por meses está obligado al SII y presenta el modelo 417 o 418
 * (Decreto 268/2011, arts. 57.5 y 49.5), así que el plugin no admite períodos mensuales ni
 * rangos de fechas libres.
 *
 * @see https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-420
 */
class Modelo420 extends Controller
{
    /** @var bool */
    public bool $allowDelete = false;

    /** @var array */
    public array $auxRegiva = [];

    /** @var ?DeclaracionIGIC */
    public ?DeclaracionIGIC $declaracion = null;

    /** @var string */
    public string $fechaDesde = '';

    /** @var string */
    public string $fechaHasta = '';

    /** @var string */
    public string $periodo = '';

    /** @var RegularizacionImpuesto */
    public RegularizacionImpuesto $regiva;

    /** @var ?RegularizacionImpuesto */
    public ?RegularizacionImpuesto $selectedRegiva = null;

    /** @var IGICHelper */
    protected IGICHelper $helper;

    /** @var ?array */
    private ?array $casillas = null;

    /** @var array */
    private array $desgloseCompras = [];

    /** @var array */
    private array $desgloseVentas = [];

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

        $this->allowDelete = (bool) $this->permissions->allowDelete;
        $this->helper = new IGICHelper();
        $this->regiva = new RegularizacionImpuesto();

        $this->setPeriodo();

        // regularización seleccionada
        $id = (int) $this->request()->query('id', 0);
        if ($id > 0) {
            $this->loadRegiva($id);
        }

        if (false === $this->execAction($this->request()->input('proceso', ''))) {
            return;
        }

        $this->view('Modelo420.html.twig');
    }

    /**
     * Obtiene todas las regularizaciones existentes.
     *
     * @return RegularizacionImpuesto[]
     */
    public function allRegularizaciones(): array
    {
        return RegularizacionImpuesto::all(
            [Where::eq('idempresa', $this->empresa->idempresa)],
            ['fechainicio' => 'DESC'],
            0,
            50
        );
    }

    /**
     * Casillas del Modelo 420 de la regularización seleccionada.
     */
    public function casillas(): array
    {
        if (null === $this->casillas && $this->selectedRegiva !== null) {
            $this->casillas = (new CasillasModelo420($this->helper))->calcular(
                $this->desgloseIGICVentas(),
                $this->desgloseIGICCompras(),
                $this->selectedRegiva->fechafin
            );
        }

        return $this->casillas ?? [];
    }

    /**
     * Líneas de compra del período seleccionado que no entran en el cálculo.
     */
    public function excluidasCompras(): array
    {
        if ($this->selectedRegiva === null) {
            return [];
        }

        return $this->helper->excluidasCompras(
            $this->selectedRegiva->fechainicio,
            $this->selectedRegiva->fechafin,
            (int) $this->selectedRegiva->idempresa
        );
    }

    /**
     * Líneas de venta del período seleccionado que no entran en el cálculo.
     */
    public function excluidasVentas(): array
    {
        if ($this->selectedRegiva === null) {
            return [];
        }

        return $this->helper->excluidasVentas(
            $this->selectedRegiva->fechainicio,
            $this->selectedRegiva->fechafin,
            (int) $this->selectedRegiva->idempresa
        );
    }

    /**
     * Plazo de presentación del trimestre seleccionado (Decreto 268/2011, art. 57.6).
     */
    public function plazo(): array
    {
        $periodo = $this->selectedRegiva->periodo ?? $this->periodo;
        $fecha = $this->selectedRegiva->fechainicio ?? $this->fechaDesde;
        if (false === in_array($periodo, IGICHelper::PERIODOS_420, true)) {
            return [];
        }

        return $this->helper->plazoPresentacion($periodo, (int) date('Y', strtotime($fecha)));
    }

    /**
     * Obtiene el desglose de IGIC de las compras para la regularización seleccionada.
     */
    public function desgloseIGICCompras(): array
    {
        if (empty($this->desgloseCompras) && $this->selectedRegiva !== null) {
            $this->desgloseCompras = $this->helper->desgloseIGICCompras(
                $this->selectedRegiva->fechainicio,
                $this->selectedRegiva->fechafin,
                (int) $this->selectedRegiva->idempresa
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
                $this->selectedRegiva->fechafin,
                (int) $this->selectedRegiva->idempresa
            );
        }
        return $this->desgloseVentas;
    }

    /**
     * Obtiene las facturas de cliente de la declaración seleccionada.
     */
    public function getFacturasClienteModelo(): array
    {
        return $this->declaracion ? $this->declaracion->getFacturasCliente() : [];
    }

    /**
     * Obtiene las facturas de proveedor de la declaración seleccionada.
     */
    public function getFacturasProveedorModelo(): array
    {
        return $this->declaracion ? $this->declaracion->getFacturasProveedor() : [];
    }

    /**
     * Devuelve las partidas del asiento de la regularización seleccionada.
     *
     * @return Partida[]
     */
    public function getPartidas(): array
    {
        return $this->selectedRegiva ? RegularizacionIGIC::getPartidas($this->selectedRegiva) : [];
    }

    /**
     * Calcula el total del IGIC deducible.
     */
    public function totalDeducible(): float
    {
        return $this->helper->calcularTotalDeducible($this->desgloseIGICCompras());
    }

    /**
     * Calcula el total del IGIC devengado.
     */
    public function totalDevengado(): float
    {
        return $this->helper->calcularTotalDevengado($this->desgloseIGICVentas());
    }

    /**
     * Calcula la previsualización del asiento de regularización.
     */
    protected function completarRegiva(): void
    {
        $idempresa = (int) $this->empresa->idempresa;
        if ($this->helper->hayFacturasSinAsiento($this->fechaDesde, $this->fechaHasta, $idempresa)) {
            Tools::log()->error('facturas-sin-asiento');
            return;
        }

        $eje = $this->getEjercicioByFecha($this->fechaDesde);
        if (null === $eje) {
            Tools::log()->error('ejercicio-cerrado');
            return;
        }

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
     * Crea un modelo rectificativo de la declaración seleccionada.
     */
    protected function crearRectificativo(): void
    {
        $nuevo = $this->declaracion->crearRectificativo();
        if (null === $nuevo) {
            Tools::log()->error('error-crear-rectificativo');
            return;
        }

        $this->declaracion = $nuevo;
        Tools::log()->notice('modelo-rectificativo-creado');
    }

    /**
     * Genera el fichero para la ATC y lo prepara como descarga.
     */
    protected function descargarATC(): void
    {
        $idempresa = $this->declaracion->getIdEmpresa();
        $generator = new ATCFileGenerator($this->declaracion);
        $generator->setDesgloseVentas($this->helper->desgloseIGICVentas(
            $this->declaracion->fechainicio,
            $this->declaracion->fechafin,
            $idempresa
        ))->setDesgloseCompras($this->helper->desgloseIGICCompras(
            $this->declaracion->fechainicio,
            $this->declaracion->fechafin,
            $idempresa
        ));

        $content = $generator->generate();
        $this->response()
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . $generator->getFilename() . '"')
            ->header('Content-Length', (string) strlen($content))
            ->header('Cache-Control', 'no-cache, must-revalidate')
            ->setContent($content)
            ->send();
    }

    /**
     * Elimina la regularización seleccionada.
     */
    protected function eliminarRegiva(): void
    {
        if (false === $this->allowDelete) {
            Tools::log()->warning('not-allowed-delete');
            return;
        }

        if ((new RegularizacionIGIC($this->helper))->eliminar($this->selectedRegiva)) {
            Tools::log()->notice('regularizacion-eliminada');
            $this->selectedRegiva = null;
            $this->declaracion = null;
        }
    }

    /**
     * Ejecuta la acción solicitada. Devuelve false si ya se ha enviado la respuesta.
     */
    protected function execAction(string $action): bool
    {
        if ($this->request()->query('download-atc') === '1' && $this->declaracion) {
            $this->descargarATC();
            return false;
        }

        if ($action === 'comprobar') {
            $this->completarRegiva();
            return true;
        }

        $acciones = ['guardar', 'eliminar', 'marcar-presentado', 'crear-rectificativo'];
        if (false === in_array($action, $acciones, true) || false === $this->validateFormToken()) {
            return true;
        }

        if ($action === 'guardar') {
            $this->guardarRegiva();
        } elseif ($action === 'eliminar' && $this->selectedRegiva) {
            $this->eliminarRegiva();
        } elseif ($action === 'marcar-presentado' && $this->declaracion) {
            $this->marcarPresentado();
        } elseif ($action === 'crear-rectificativo' && $this->declaracion) {
            $this->crearRectificativo();
        }

        return true;
    }

    /**
     * Obtiene el ejercicio abierto de la empresa que contiene la fecha indicada.
     */
    protected function getEjercicioByFecha(string $fecha): ?Ejercicio
    {
        $ejercicio = new Ejercicio();
        $ejercicio->idempresa = $this->empresa->idempresa;
        if (false === $ejercicio->loadFromDate($fecha, true, false)) {
            return null;
        }

        return $ejercicio;
    }

    /**
     * Guarda la regularización creando el asiento contable y la declaración.
     */
    protected function guardarRegiva(): void
    {
        $eje = $this->getEjercicioByFecha($this->fechaDesde);
        if (null === $eje) {
            Tools::log()->error('ejercicio-cerrado');
            return;
        }

        $regiva = (new RegularizacionIGIC($this->helper))->guardar(
            $eje,
            $this->fechaDesde,
            $this->fechaHasta,
            $this->periodo
        );
        if (null === $regiva) {
            return;
        }

        Tools::log()->notice('regularizacion-guardada');
        $this->loadRegiva((int) $regiva->idregiva);
    }

    protected function loadRegiva(int $id): void
    {
        $regiva = new RegularizacionImpuesto();
        if (false === $regiva->load($id)) {
            Tools::log()->warning('regularizacion-no-encontrada');
            return;
        }

        $this->selectedRegiva = $regiva;
        $this->declaracion = RegularizacionIGIC::getDeclaracion($id);
    }

    /**
     * Fija el trimestre y sus fechas a partir del período y el año solicitados.
     *
     * Las fechas siempre son las del trimestre natural (Decreto 268/2011, art. 57.5).
     */
    protected function setPeriodo(): void
    {
        $periodoDefault = $this->helper->calcularPeriodoActual();
        $periodo = (string) $this->request()->input('periodo', '');
        $this->periodo = in_array($periodo, IGICHelper::PERIODOS_420, true) ? $periodo : $periodoDefault['periodo'];

        $anyo = (int) $this->request()->input('anyo', 0);
        if ($anyo < 1) {
            $desde = (string) $this->request()->input('desde', '');
            $anyo = strtotime($desde) ? (int) date('Y', strtotime($desde)) : 0;
        }
        if ($anyo < 1) {
            $anyo = (int) date('Y', strtotime($periodoDefault['fecha_desde']));
        }

        $fechas = $this->helper->fechasPorPeriodo($this->periodo, $anyo);
        $this->fechaDesde = $fechas['fecha_desde'];
        $this->fechaHasta = $fechas['fecha_hasta'];
    }

    /**
     * Marca la declaración seleccionada como presentada.
     */
    protected function marcarPresentado(): void
    {
        $numeroReferencia = $this->request()->input('numeroreferencia', '');
        $fechaPresentacion = $this->request()->input('fechapresentacion') ?: Tools::date();

        if ($this->declaracion->marcarPresentado($numeroReferencia ?: null, $fechaPresentacion)) {
            Tools::log()->notice('modelo-marcado-presentado');
            return;
        }

        Tools::log()->error('error-marcar-presentado');
    }
}
