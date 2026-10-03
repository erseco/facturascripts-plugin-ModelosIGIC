<?php

/**
 * This file is part of ModelosIGIC plugin for FacturaScripts.
 * Copyright (C) 2026 Ernesto Serrano <info@ernesto.es>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\ModelosIGIC\Controller\Modelo420;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use FacturaScripts\Plugins\ModelosIGIC\Lib\RegularizacionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Flujo completo de la pantalla del Modelo 420.
 */
final class Modelo420ControllerTest extends TestCase
{
    use ModelosIGICFixtures;

    protected function setUp(): void
    {
        $this->login();
    }

    protected function tearDown(): void
    {
        $this->cleanFixtures();
    }

    public function testPantallaInicial(): void
    {
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);

        $this->assertStringContainsString('modalNuevaRegiva', $html);
        $this->assertNull($controller->selectedRegiva);
        $this->assertMatchesRegularExpression('/^T[1-4]$/', $controller->periodo);
        $this->assertIsArray($controller->allRegularizaciones());
        $this->assertSame([], $controller->casillas());
        $this->assertSame([], $controller->excluidasVentas());
        $this->assertSame([], $controller->excluidasCompras());
        $this->assertNotEmpty($controller->plazo());
    }

    public function testCalcularMuestraLaPrevisualizacion(): void
    {
        $this->makeTrimestre();
        $this->post(['proceso' => 'comprobar', 'periodo' => 'T1', 'desde' => '2090-01-01', 'hasta' => '2090-03-31']);

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);

        $this->assertCount(3, $controller->auxRegiva);
        $this->assertStringContainsString('getOrCreateInstance', $html);
    }

    public function testCalcularSinEjercicioAbierto(): void
    {
        $this->post(['proceso' => 'comprobar', 'periodo' => 'T1', 'desde' => '2190-01-01', 'hasta' => '2190-03-31']);

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertSame([], $controller->auxRegiva);
    }

    public function testCalcularSinDatos(): void
    {
        $this->ejercicio();
        $this->post(['proceso' => 'comprobar', 'periodo' => 'T4', 'desde' => '2090-10-01', 'hasta' => '2090-12-31']);

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertSame([], $controller->auxRegiva);
    }

    public function testGuardarVerMarcarRectificarYDescargar(): void
    {
        $this->makeTrimestre();

        // guardar
        $this->post([
            'proceso' => 'guardar', 'periodo' => 'T1', 'desde' => '2090-01-01', 'hasta' => '2090-03-31',
            'multireqtoken' => $this->formToken(),
        ]);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);
        $this->assertNotNull($controller->selectedRegiva, $this->recentLog());
        $this->assertNotNull($controller->declaracion);
        $this->assertCount(3, $controller->getPartidas());
        $this->assertStringContainsString('modalMarcarPresentado', $html);
        $idregiva = (int) $controller->selectedRegiva->idregiva;

        // ver la regularización guardada
        $this->get(['id' => $idregiva]);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);
        $this->assertEqualsWithDelta(70.0, $controller->totalDevengado(), 0.001);
        $this->assertEqualsWithDelta(28.0, $controller->totalDeducible(), 0.001);
        $this->assertCount(1, $controller->getFacturasClienteModelo());
        $this->assertCount(1, $controller->getFacturasProveedorModelo());
        $this->assertStringContainsString('4770000007', $html);

        // casillas del modelo y plazo de presentación (Decreto 268/2011, art. 57.6)
        $casillas = $controller->casillas();
        $this->assertSame(['01', '02', '03'], $casillas['filas'][0]['casillas']);
        $this->assertEqualsWithDelta(70.0, $casillas['casillas']['25']['importe'], 0.001);
        $this->assertEqualsWithDelta(42.0, $casillas['casillas']['45']['importe'], 0.001);
        $this->assertSame('I', $casillas['resultado']);
        $this->assertSame(['desde' => '2090-04-01', 'hasta' => '2090-04-20'], $controller->plazo());
        $this->assertSame([], $controller->excluidasVentas());
        $this->assertSame([], $controller->excluidasCompras());
        $this->assertStringContainsString(Tools::lang()->trans('casilla-420-45'), $html);
        $this->assertStringContainsString('20-04-2090', $html);

        // marcar como presentado
        $this->post([
            'proceso' => 'marcar-presentado', 'numeroreferencia' => 'ATC-2090-1', 'fechapresentacion' => '2090-04-15',
            'multireqtoken' => $this->formToken(),
        ], ['id' => $idregiva]);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);
        $this->assertSame('presentado', $controller->declaracion->estado);
        $this->assertSame('ATC-2090-1', $controller->declaracion->numeroreferencia);
        $this->assertStringContainsString('modalCrearRectificativo', $html);

        // crear rectificativo
        $this->post(['proceso' => 'crear-rectificativo', 'multireqtoken' => $this->formToken()], ['id' => $idregiva]);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);
        $this->assertTrue($controller->declaracion->esRectificativo());

        // el listado indica que la regularización tiene un rectificativo en borrador (#13)
        $this->get([]);
        $html = $this->runController(new Modelo420('Modelo420', '/Modelo420'));
        $this->assertStringContainsString(Tools::lang()->trans('modelo-rectificativo'), $html);

        // no hay programa de ayuda para el ejercicio de pruebas: no se ofrece el fichero
        $this->assertFalse($controller->ficheroATCDisponible());
        $this->post(['proceso' => 'descargar-atc', 'multireqtoken' => $this->formToken()], ['id' => $idregiva]);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);
        $this->assertStringNotContainsString('modalFicheroATC', $html);
        $this->assertStringContainsString(
            Tools::lang()->trans('fichero-atc-ejercicio-no-soportado'),
            $this->recentLog()
        );
    }

    public function testGuardarSinTokenNoHaceNada(): void
    {
        $this->makeTrimestre();
        $this->post(['proceso' => 'guardar', 'periodo' => 'T1', 'desde' => '2090-01-01', 'hasta' => '2090-03-31']);

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertNull($controller->selectedRegiva);
        $this->assertSame(0, $this->contarRegularizaciones());
    }

    public function testGuardarSinEjercicioAbierto(): void
    {
        $this->post([
            'proceso' => 'guardar', 'periodo' => 'T1', 'desde' => '2190-01-01', 'hasta' => '2190-03-31',
            'multireqtoken' => $this->formToken(),
        ]);

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertNull($controller->selectedRegiva);
    }

    public function testGuardarSinDatos(): void
    {
        $this->ejercicio();
        $this->post([
            'proceso' => 'guardar', 'periodo' => 'T4', 'desde' => '2090-10-01', 'hasta' => '2090-12-31',
            'multireqtoken' => $this->formToken(),
        ]);

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertNull($controller->selectedRegiva);
    }

    public function testEliminar(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->post(['proceso' => 'eliminar', 'multireqtoken' => $this->formToken()], ['id' => $regiva->idregiva]);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertNull($controller->selectedRegiva);
        $this->assertSame(0, $this->contarRegularizaciones());
    }

    public function testEliminarSinPermiso(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->post(['proceso' => 'eliminar', 'multireqtoken' => $this->formToken()], ['id' => $regiva->idregiva]);
        $controller = new class ('Modelo420', '/Modelo420') extends Modelo420 {
            protected function execAction(string $action): bool
            {
                $this->allowDelete = false;
                return parent::execAction($action);
            }
        };
        $this->runController($controller);

        $this->assertSame(1, $this->contarRegularizaciones());
    }

    public function testListadoMuestraResultadoYEstado(): void
    {
        $this->makeTrimestre();
        (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);

        $this->assertStringContainsString(Tools::money(42.0), $html);
        $this->assertStringContainsString(Tools::lang()->trans('resultado-tipo-I'), $html);
        $this->assertSame('C', $controller->tipoResultado(-5.0));
    }

    public function testActualizarRecalculaConLasFacturasNuevas(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');
        $this->makeFacturaCliente('10-03-' . static::$year, 1000.0);

        $this->post(['proceso' => 'actualizar', 'multireqtoken' => $this->formToken()], ['id' => $regiva->idregiva]);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertNotNull($controller->declaracion, $this->recentLog());
        $this->assertEqualsWithDelta(112.0, $controller->declaracion->resultado, 0.001);
        // el ejercicio de pruebas es futuro: el trimestre no ha terminado
        $this->assertTrue($controller->periodoAbierto());
        $this->assertSame(1, $this->contarRegularizaciones());
    }

    public function testActualizarSinPermiso(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->post(['proceso' => 'actualizar', 'multireqtoken' => $this->formToken()], ['id' => $regiva->idregiva]);
        $controller = new class ('Modelo420', '/Modelo420') extends Modelo420 {
            protected function execAction(string $action): bool
            {
                $this->allowUpdate = false;
                return parent::execAction($action);
            }
        };
        $this->runController($controller);

        $this->assertSame((int) $regiva->idregiva, (int) $controller->selectedRegiva->idregiva);
    }

    public function testGuardarSinPermiso(): void
    {
        $this->makeTrimestre();
        $this->post([
            'proceso' => 'guardar', 'periodo' => 'T1', 'desde' => '2090-01-01', 'hasta' => '2090-03-31',
            'multireqtoken' => $this->formToken(),
        ]);
        $this->runController($this->controladorSoloLectura());

        $this->assertSame(0, $this->contarRegularizaciones());
    }

    public function testMarcarPresentadoSinPermiso(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->post([
            'proceso' => 'marcar-presentado', 'fechapresentacion' => '2090-04-15',
            'multireqtoken' => $this->formToken(),
        ], ['id' => $regiva->idregiva]);
        $this->runController($this->controladorSoloLectura());

        $this->assertSame('borrador', RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva)->estado);
    }

    public function testCrearRectificativoSinPermiso(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');
        RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva)->marcarPresentado('REF-1', '2090-04-15');

        $this->post(
            ['proceso' => 'crear-rectificativo', 'multireqtoken' => $this->formToken()],
            ['id' => $regiva->idregiva]
        );
        $this->runController($this->controladorSoloLectura());

        $declaracion = RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva);
        $this->assertFalse($declaracion->esRectificativo());
        $this->assertSame('presentado', $declaracion->estado);
    }

    public function testDescargarFicheroSinPermiso(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->post(['proceso' => 'descargar-atc', 'multireqtoken' => $this->formToken()], ['id' => $regiva->idregiva]);
        MiniLog::clear();
        $this->runController($this->controladorSoloLectura());

        $this->assertStringContainsString(Tools::lang()->trans('not-allowed-modify'), $this->recentLog());
        $this->assertStringNotContainsString(
            Tools::lang()->trans('fichero-atc-ejercicio-no-soportado'),
            $this->recentLog()
        );
    }

    public function testRegularizacionDeOtraEmpresaNoSeCarga(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->post(['proceso' => 'eliminar', 'multireqtoken' => $this->formToken()], ['id' => $regiva->idregiva]);
        $controller = new class ('Modelo420', '/Modelo420') extends Modelo420 {
            protected function loadRegiva(int $id): void
            {
                // el usuario trabaja con otra empresa
                $this->empresa = clone $this->empresa;
                $this->empresa->idempresa = (int) $this->empresa->idempresa + 1000;
                parent::loadRegiva($id);
            }
        };
        $this->runController($controller);

        $this->assertNull($controller->selectedRegiva);
        $this->assertNull($controller->declaracion);
        $this->assertSame(1, $this->contarRegularizaciones());
    }

    public function testAvisaSiLaContabilidadNoCuadraConLasFacturas(): void
    {
        $this->makeTrimestre();
        $this->makeAsientoManualIGIC('15-02-' . static::$year, 10.0);

        // en la previsualización
        $this->post(['proceso' => 'comprobar', 'periodo' => 'T1', 'desde' => '2090-01-01', 'hasta' => '2090-03-31']);
        MiniLog::clear();
        $this->runController(new Modelo420('Modelo420', '/Modelo420'));
        $this->assertStringContainsString(Tools::lang()->trans('descuadre-contable-facturas', [
            '%diferencia%' => Tools::money(10.0),
        ]), $this->recentLog());

        // y en la regularización guardada
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');
        $this->get(['id' => $regiva->idregiva]);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);
        $this->assertEqualsWithDelta(10.0, $controller->diferenciaContable(), 0.001);
        $this->assertStringContainsString('alertDescuadreContable', $html);
    }

    public function testSinDescuadreNoAvisa(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->get(['id' => $regiva->idregiva]);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);

        $this->assertEqualsWithDelta(0.0, $controller->diferenciaContable(), 0.001);
        $this->assertStringNotContainsString('alertDescuadreContable', $html);
    }

    public function testLasFacturasSeLeenUnaVezPorCarga(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->get(['id' => $regiva->idregiva]);
        $helper = new class () extends IGICHelper {
            public int $lecturas = 0;

            protected function facturasCliente(string $fechaInicio, string $fechaFin, ?int $idempresa): array
            {
                $this->lecturas++;
                return parent::facturasCliente($fechaInicio, $fechaFin, $idempresa);
            }

            protected function facturasProveedor(string $fechaInicio, string $fechaFin, ?int $idempresa): array
            {
                $this->lecturas++;
                return parent::facturasProveedor($fechaInicio, $fechaFin, $idempresa);
            }
        };
        $controller = new class ('Modelo420', '/Modelo420') extends Modelo420 {
            public IGICHelper $helperPruebas;

            protected function nuevoHelper(): IGICHelper
            {
                return $this->helperPruebas;
            }
        };
        $controller->helperPruebas = $helper;
        $this->runController($controller);

        // una lectura de las ventas y otra de las compras
        $this->assertSame(2, $helper->lecturas);
    }

    public function testConfirmacionesEscapadasParaJavaScript(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->get(['id' => $regiva->idregiva]);
        $html = $this->runController(new Modelo420('Modelo420', '/Modelo420'));

        $twig = new Environment(new ArrayLoader(['js' => "{{ texto|e('js') }}"]));
        foreach (['confirmar-actualizar', 'confirmar-eliminar'] as $clave) {
            $texto = $twig->render('js', ['texto' => Tools::lang()->trans($clave)]);
            $this->assertStringContainsString("confirm('" . $texto . "')", $html, $clave);
        }
    }

    public function testSoloLecturaNoMuestraLasAcciones(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->get([]);
        $html = $this->runController($this->controladorSoloLectura());
        $this->assertStringNotContainsString('onclick="guardarRegiva()"', $html);

        $this->get(['id' => $regiva->idregiva]);
        $html = $this->runController($this->controladorSoloLectura());
        $this->assertStringNotContainsString('data-bs-target="#modalMarcarPresentado"', $html);

        RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva)->marcarPresentado('REF-1', '2090-04-15');
        $html = $this->runController($this->controladorSoloLectura());
        $this->assertStringNotContainsString('data-bs-target="#modalCrearRectificativo"', $html);
    }

    public function testRegularizacionInexistente(): void
    {
        $this->get(['id' => 999999]);

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertNull($controller->selectedRegiva);
        $this->assertSame([], $controller->getPartidas());
        $this->assertSame([], $controller->getFacturasClienteModelo());
        $this->assertSame([], $controller->getFacturasProveedorModelo());
        $this->assertSame([], $controller->desgloseIGICVentas());
        $this->assertSame([], $controller->desgloseIGICCompras());
    }

    public function testRectificarDeclaracionEnBorradorFalla(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->post(
            ['proceso' => 'crear-rectificativo', 'multireqtoken' => $this->formToken()],
            ['id' => $regiva->idregiva]
        );
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertFalse($controller->declaracion->esRectificativo());
    }

    public function testMarcarPresentadoConFallo(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $this->post(
            ['proceso' => 'marcar-presentado', 'multireqtoken' => $this->formToken()],
            ['id' => $regiva->idregiva]
        );
        $controller = new class ('Modelo420', '/Modelo420') extends Modelo420 {
            protected function loadRegiva(int $id): void
            {
                parent::loadRegiva($id);
                // una declaración que no se puede guardar
                $this->declaracion = new class () extends DeclaracionIGIC {
                    public function save(): bool
                    {
                        return false;
                    }
                };
            }
        };
        $this->runController($controller);

        $this->assertSame('borrador', RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva)->estado);
    }

    /**
     * Controlador de un usuario con permiso de solo lectura.
     */
    private function controladorSoloLectura(): Modelo420
    {
        return new class ('Modelo420', '/Modelo420') extends Modelo420 {
            protected function execAction(string $action): bool
            {
                $this->allowDelete = false;
                $this->allowUpdate = false;
                return parent::execAction($action);
            }
        };
    }

    /**
     * Asiento manual que lleva IGIC repercutido a la subcuenta sin pasar por ninguna factura.
     */
    private function makeAsientoManualIGIC(string $fecha, float $importe): void
    {
        $ejercicio = $this->ejercicio();
        $helper = new IGICHelper();
        $asiento = new Asiento();
        $asiento->idempresa = $ejercicio->idempresa;
        $asiento->codejercicio = $ejercicio->codejercicio;
        $asiento->concepto = 'Ajuste manual de IGIC';
        $asiento->fecha = $fecha;
        $this->assertTrue($asiento->save());
        $this->fixtures[] = $asiento;

        $haber = $asiento->getNewLine($helper->getSubcuentaEspecial('IVAREP', $ejercicio->codejercicio));
        $haber->haber = $importe;
        $this->assertTrue($haber->save());
        $debe = $asiento->getNewLine($helper->getSubcuentaEspecial('CAJA', $ejercicio->codejercicio));
        $debe->debe = $importe;
        $this->assertTrue($debe->save());
    }

    private function contarRegularizaciones(): int
    {
        return RegularizacionImpuesto::count([
            Where::gte('fechainicio', static::$year . '-01-01'),
            Where::lte('fechafin', static::$year . '-12-31'),
        ]);
    }

    private function get(array $query): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $query;
        $_POST = [];
        $_REQUEST = $query;
    }

    private function post(array $data, array $query = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = $query;
        $_POST = $data;
        $_REQUEST = array_merge($query, $data);
    }
}
