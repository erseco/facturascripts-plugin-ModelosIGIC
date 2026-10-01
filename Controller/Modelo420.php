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
use FacturaScripts\Plugins\ModelosIGIC\Lib\ComparativaIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use FacturaScripts\Plugins\ModelosIGIC\Lib\ListasATC;
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

    /** @var bool */
    public bool $allowUpdate = false;

    /** @var array */
    public array $auxRegiva = [];

    /** @var array Datos del formulario del fichero para el programa de ayuda */
    public array $datosATC = [];

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

    /** @var ?array Análisis de las compras del período, calculado una sola vez por carga */
    private ?array $analisisCompras = null;

    /** @var ?array Análisis de las ventas del período, calculado una sola vez por carga */
    private ?array $analisisVentas = null;

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
        $this->allowUpdate = (bool) $this->permissions->allowUpdate;
        $this->helper = $this->nuevoHelper();
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
     * Datos para el formulario del fichero: los enviados, los guardados de la empresa o los que
     * se pueden tomar de la ficha de la empresa (NIF, código postal y, si no es persona física,
     * la razón social). El resto los aporta el declarante.
     */
    public function datosFichero(): array
    {
        if (!empty($this->datosATC)) {
            return $this->datosATC;
        }

        $guardados = json_decode((string) Tools::settings('modelosigic', $this->claveDatosFichero(), ''), true);
        if (is_array($guardados)) {
            return $guardados;
        }

        $nif = ATCFileGenerator::texto((string) $this->empresa->cifnif, 9);
        $nombre = ATCFileGenerator::texto((string) $this->empresa->nombre, 75);
        return [
            'nif' => $nif,
            'nrs' => ATCFileGenerator::esPersonaFisica($nif) ? '' : $nombre,
            'cp' => (string) $this->empresa->codpostal,
        ];
    }

    /**
     * Indica si se puede generar el fichero para el programa de ayuda de la declaración.
     */
    public function ficheroATCDisponible(): bool
    {
        return $this->declaracion !== null
            && isset(ATCFileGenerator::PROGRAMAS[date('Y', strtotime((string) $this->declaracion->fechainicio))]);
    }

    public function municipiosCanarias(): array
    {
        return ListasATC::municipiosCanarias();
    }

    public function siglasVia(): array
    {
        return ListasATC::SIGLAS;
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
     * Declaración del Modelo 420 de una regularización, para mostrar su resultado en el listado.
     */
    public function declaracionDe(int $idregiva): ?DeclaracionIGIC
    {
        return RegularizacionIGIC::getDeclaracion($idregiva);
    }

    /**
     * Indica si el trimestre de la regularización seleccionada todavía no ha terminado.
     */
    public function periodoAbierto(): bool
    {
        return $this->selectedRegiva !== null
            && strtotime((string) $this->selectedRegiva->fechafin) >= strtotime(Tools::date());
    }

    /**
     * Tipo de resultado (I, C) de un importe de la casilla 45.
     */
    public function tipoResultado(float $resultado): string
    {
        return (new CasillasModelo420($this->helper))->tipoResultado($resultado);
    }

    /**
     * Returns the historical Model 420 comparison for the current company.
     */
    public function comparativaHistorica(): array
    {
        [$desde, $hasta] = $this->rangoComparativa();
        $declaraciones = array_filter(
            DeclaracionIGIC::all([Where::eq('tipo', '420')], ['fechainicio' => 'ASC']),
            fn (DeclaracionIGIC $declaracion): bool => $declaracion->getIdEmpresa() === (int) $this->empresa->idempresa
        );

        return ComparativaIGIC::modelo420($declaraciones, $desde, $hasta);
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
        return $this->analisisCompras()['excluidas'];
    }

    /**
     * Líneas de venta del período seleccionado que no entran en el cálculo.
     */
    public function excluidasVentas(): array
    {
        return $this->analisisVentas()['excluidas'];
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
        return $this->analisisCompras()['igic'];
    }

    /**
     * Obtiene el desglose de IGIC de las ventas para la regularización seleccionada.
     */
    public function desgloseIGICVentas(): array
    {
        return $this->analisisVentas()['igic'];
    }

    /**
     * Diferencia entre el resultado contable que regulariza el asiento y el de las facturas que
     * declara el modelo. Distinta de cero si hay asientos manuales en las subcuentas de IGIC.
     */
    public function diferenciaContable(): float
    {
        if ($this->selectedRegiva === null) {
            return 0.0;
        }

        $contable = $this->helper->resultadoContable(
            $this->selectedRegiva->fechainicio,
            $this->selectedRegiva->fechafin,
            $this->selectedRegiva->codejercicio,
            empty($this->selectedRegiva->idasiento) ? null : (int) $this->selectedRegiva->idasiento
        );

        return Tools::round($contable - ($this->totalDevengado() - $this->totalDeducible()));
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
     * Recalcula la regularización seleccionada con las facturas actuales del período.
     */
    protected function actualizarRegiva(): void
    {
        $eje = $this->getEjercicioByFecha((string) $this->selectedRegiva->fechainicio);
        if (null === $eje) {
            Tools::log()->error('ejercicio-cerrado');
            return;
        }

        $regiva = (new RegularizacionIGIC($this->helper))->actualizar($this->selectedRegiva, $eje);
        if (null === $regiva) {
            return;
        }

        Tools::log()->notice('regularizacion-actualizada');
        $this->loadRegiva((int) $regiva->idregiva);
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
            return;
        }

        $ventas = $this->helper->desgloseIGICVentas($this->fechaDesde, $this->fechaHasta, $idempresa);
        $compras = $this->helper->desgloseIGICCompras($this->fechaDesde, $this->fechaHasta, $idempresa);
        $facturas = $this->helper->calcularTotalDevengado($ventas) - $this->helper->calcularTotalDeducible($compras);
        $contable = $this->helper->resultadoContable($this->fechaDesde, $this->fechaHasta, $eje->codejercicio);
        $diferencia = Tools::round($contable - $facturas);
        if (false === Tools::floatCmp($diferencia, 0.0, 2)) {
            Tools::log()->warning('descuadre-contable-facturas', ['%diferencia%' => Tools::money($diferencia)]);
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
     * Genera el fichero para importar en el programa de ayuda de la ATC (experimental).
     *
     * Devuelve false si se ha enviado el fichero; si faltan datos, muestra los errores y vuelve
     * a la página con el formulario relleno.
     */
    protected function descargarATC(): bool
    {
        $this->datosATC = $this->datosFicheroDesdeRequest();
        $generator = (new ATCFileGenerator($this->declaracion))
            ->setCasillas($this->casillas())
            ->setDatos($this->datosATC);

        $errores = $generator->validar();
        if (!empty($errores)) {
            foreach ($errores as $error) {
                Tools::log()->warning($error);
            }
            return true;
        }

        $this->guardarDatosFichero();
        $content = $generator->generate();
        $this->response()
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . $generator->getFilename() . '"')
            ->header('Content-Length', (string) strlen($content))
            ->header('Cache-Control', 'no-cache, must-revalidate')
            ->setContent($content)
            ->send();

        return false;
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
            $this->analisisCompras = $this->analisisVentas = $this->casillas = null;
        }
    }

    /**
     * Ejecuta la acción solicitada. Devuelve false si ya se ha enviado la respuesta.
     */
    protected function execAction(string $action): bool
    {
        if ($action === 'comprobar') {
            $this->completarRegiva();
            return true;
        }

        $acciones = ['guardar', 'actualizar', 'eliminar', 'marcar-presentado', 'crear-rectificativo', 'descargar-atc'];
        if (false === in_array($action, $acciones, true) || false === $this->validateFormToken()) {
            return true;
        }

        // todas estas acciones escriben datos: eliminar exige además el permiso de borrado
        if (false === $this->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        }

        if ($action === 'guardar') {
            $this->guardarRegiva();
        } elseif ($action === 'actualizar' && $this->selectedRegiva) {
            $this->actualizarRegiva();
        } elseif ($action === 'eliminar' && $this->selectedRegiva) {
            $this->eliminarRegiva();
        } elseif ($action === 'marcar-presentado' && $this->declaracion) {
            $this->marcarPresentado();
        } elseif ($action === 'crear-rectificativo' && $this->declaracion) {
            $this->crearRectificativo();
        } elseif ($action === 'descargar-atc' && $this->declaracion) {
            return $this->descargarATC();
        }

        return true;
    }

    /**
     * Returns the normalized year range requested for the comparison.
     */
    protected function rangoComparativa(): array
    {
        $actual = (int) date('Y');
        $desde = (int) $this->request()->inputOrQuery('comparar_desde', $actual - 4);
        $hasta = (int) $this->request()->inputOrQuery('comparar_hasta', $actual);

        return $desde <= $hasta ? [$desde, $hasta] : [$hasta, $desde];
    }

    protected function claveDatosFichero(): string
    {
        return 'fichero-atc-' . (int) $this->empresa->idempresa;
    }

    /**
     * Datos del formulario del fichero enviados por el usuario.
     */
    protected function datosFicheroDesdeRequest(): array
    {
        $datos = [];
        $campos = ['nif', 'nrs', 'svp', 'nvp', 'npk', 'esc', 'pis', 'pue', 'pop', 'cmu', 'cp', 'tel',
            'c42', 'c43', 'c44', 'c46', 'c47', 'nja', 'tipo', 'fpa', 'iban'];
        foreach ($campos as $campo) {
            $datos[$campo] = trim((string) $this->request()->input($campo, ''));
        }
        $datos['complementaria'] = (bool) $this->request()->input('complementaria', false);

        return $datos;
    }

    /**
     * Guarda los datos identificativos y la forma de pago de la empresa para el siguiente trimestre.
     *
     * El IBAN no se guarda: la configuración no va cifrada y se pide en cada presentación.
     */
    protected function guardarDatosFichero(): void
    {
        $guardar = array_intersect_key($this->datosATC, array_flip(
            ['nif', 'nrs', 'svp', 'nvp', 'npk', 'esc', 'pis', 'pue', 'pop', 'cmu', 'cp', 'tel', 'fpa']
        ));
        Tools::settingsSet('modelosigic', $this->claveDatosFichero(), json_encode($guardar));
        Tools::settingsSave();
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

    /**
     * Carga una regularización de la empresa activa.
     */
    protected function loadRegiva(int $id): void
    {
        $regiva = new RegularizacionImpuesto();
        if (false === $regiva->load($id) || $regiva->idempresa != $this->empresa->idempresa) {
            Tools::log()->warning('regularizacion-no-encontrada');
            return;
        }

        $this->selectedRegiva = $regiva;
        $this->declaracion = RegularizacionIGIC::getDeclaracion($id);
        $this->analisisCompras = $this->analisisVentas = $this->casillas = null;
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

    protected function nuevoHelper(): IGICHelper
    {
        return new IGICHelper();
    }

    private function analisisCompras(): array
    {
        if (null === $this->analisisCompras) {
            $this->analisisCompras = $this->selectedRegiva === null ? ['igic' => [], 'excluidas' => []] :
                $this->helper->analisisCompras(
                    $this->selectedRegiva->fechainicio,
                    $this->selectedRegiva->fechafin,
                    (int) $this->selectedRegiva->idempresa
                );
        }

        return $this->analisisCompras;
    }

    private function analisisVentas(): array
    {
        if (null === $this->analisisVentas) {
            $this->analisisVentas = $this->selectedRegiva === null ? ['igic' => [], 'excluidas' => []] :
                $this->helper->analisisVentas(
                    $this->selectedRegiva->fechainicio,
                    $this->selectedRegiva->fechafin,
                    (int) $this->selectedRegiva->idempresa
                );
        }

        return $this->analisisVentas;
    }
}
