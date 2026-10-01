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

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\ModelosIGIC\Controller\EditDeclaracionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Controller\ListDeclaracionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Controller\Modelo425;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use FacturaScripts\Plugins\ModelosIGIC\Lib\RegularizacionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use PHPUnit\Framework\TestCase;

/**
 * Pantalla del Modelo 425 y listado/edición de declaraciones.
 */
final class Modelo425ControllerTest extends TestCase
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

    public function testResumenAnualGuardarYMarcarPresentado(): void
    {
        $this->makeTrimestre();
        $this->makeTrimestre('05');
        $codejercicio = $this->ejercicio()->codejercicio;
        (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        // resumen del ejercicio
        $this->request(['codejercicio' => $codejercicio]);
        $controller = new Modelo425('Modelo425', '/Modelo425');
        $html = $this->runController($controller);
        $this->assertSame($codejercicio, $controller->selectedEjercicio->codejercicio);
        $this->assertNull($controller->declaracion);
        $this->assertEqualsWithDelta(140.0, $controller->totalDevengado(), 0.001);
        $this->assertEqualsWithDelta(56.0, $controller->totalDeducible(), 0.001);
        $this->assertEqualsWithDelta(84.0, $controller->resultado(), 0.001);
        $this->assertEqualsWithDelta(2000.0, $controller->totalBaseVentas(), 0.001);
        $this->assertEqualsWithDelta(800.0, $controller->totalBaseCompras(), 0.001);
        $this->assertCount(1, $controller->getModelos420());
        $this->assertNotEmpty($controller->allEjercicios());
        $this->assertStringContainsString('formEjercicio', $html);

        // casillas del resumen anual y plazo de presentación (Decreto 268/2011, art. 57.8)
        $casillas = $controller->casillas()['casillas'];
        $this->assertEqualsWithDelta(2000.0, $casillas['74']['importe'], 0.001);
        $this->assertEqualsWithDelta(84.0, $casillas['95']['importe'], 0.001);
        $this->assertEqualsWithDelta(42.0, $casillas['116']['importe'], 0.001);
        $this->assertSame(['desde' => '2091-01-01', 'hasta' => '2091-01-31'], $controller->plazo());
        $this->assertSame([], $controller->excluidasVentas());
        $this->assertSame([], $controller->excluidasCompras());
        $this->assertStringContainsString(Tools::lang()->trans('casilla-425-74'), $html);
        $this->assertStringContainsString('31-01-2091', $html);

        // guardar el 425
        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'guardar', 'multireqtoken' => $this->formToken(),
        ]);
        $controller = new Modelo425('Modelo425', '/Modelo425');
        $this->runController($controller);
        $this->assertNotNull($controller->declaracion, $this->recentLog());
        $this->assertSame('425', $controller->declaracion->tipo);
        $this->assertCount(2, $controller->getFacturasClienteModelo());
        $this->assertCount(2, $controller->getFacturasProveedorModelo());

        // no se puede guardar dos veces
        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'guardar', 'multireqtoken' => $this->formToken(),
        ]);
        $this->runController(new Modelo425('Modelo425', '/Modelo425'));
        $this->assertSame(1, $this->contar425($codejercicio));

        // marcar como presentado
        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'marcar-presentado', 'numeroreferencia' => 'ATC-425',
            'fechapresentacion' => '2091-01-20', 'multireqtoken' => $this->formToken(),
        ]);
        $controller = new Modelo425('Modelo425', '/Modelo425');
        $this->runController($controller);
        $this->assertSame('presentado', $controller->declaracion->estado);
    }

    public function testSinEjercicioSeleccionado(): void
    {
        $this->request(['codejercicio' => 'NOEX']);
        $controller = new Modelo425('Modelo425', '/Modelo425');
        $this->runController($controller);

        $this->assertNull($controller->selectedEjercicio);
        $this->assertSame([], $controller->getModelos420());
        $this->assertSame([], $controller->getFacturasClienteModelo());
        $this->assertSame([], $controller->getFacturasProveedorModelo());
        $this->assertSame(0.0, $controller->resultado());
        $this->assertSame([], $controller->casillas());
        $this->assertSame([], $controller->plazo());
        $this->assertSame([], $controller->excluidasVentas());
        $this->assertSame([], $controller->excluidasCompras());
    }

    public function testEjercicioPorDefecto(): void
    {
        $this->request([]);
        $controller = new Modelo425('Modelo425', '/Modelo425');
        $html = $this->runController($controller);

        $this->assertNotSame('', $html);
    }

    public function testGuardarConFalloNoDejaDeclaracion(): void
    {
        $this->makeTrimestre();
        $codejercicio = $this->ejercicio()->codejercicio;

        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'guardar', 'multireqtoken' => $this->formToken(),
        ]);
        $controller = new class ('Modelo425', '/Modelo425') extends Modelo425 {
            protected function nuevaDeclaracion(): DeclaracionIGIC
            {
                // una declaración que no se puede guardar
                return new class () extends DeclaracionIGIC {
                    public function save(): bool
                    {
                        return false;
                    }
                };
            }
        };
        $this->runController($controller);

        $this->assertSame(0, $this->contar425($codejercicio));
    }

    public function testMarcarPresentadoConFallo(): void
    {
        $this->makeTrimestre();
        $codejercicio = $this->ejercicio()->codejercicio;
        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'guardar', 'multireqtoken' => $this->formToken(),
        ]);
        $this->runController(new Modelo425('Modelo425', '/Modelo425'));

        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'marcar-presentado', 'multireqtoken' => $this->formToken(),
        ]);
        $controller = new class ('Modelo425', '/Modelo425') extends Modelo425 {
            protected function getDeclaracionIGICPorEjercicio(string $codejercicio): ?DeclaracionIGIC
            {
                return new class () extends DeclaracionIGIC {
                    public function save(): bool
                    {
                        return false;
                    }
                };
            }
        };
        $this->runController($controller);

        $this->assertSame('borrador', DeclaracionIGIC::findWhere([Where::eq('tipo', '425')])->estado);
    }

    public function testGuardarSinPermiso(): void
    {
        $this->makeTrimestre();
        $codejercicio = $this->ejercicio()->codejercicio;
        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'guardar', 'multireqtoken' => $this->formToken(),
        ]);
        $this->runController($this->controladorSoloLectura());

        $this->assertSame(0, $this->contar425($codejercicio));
    }

    public function testMarcarPresentadoSinPermiso(): void
    {
        $this->makeTrimestre();
        $codejercicio = $this->ejercicio()->codejercicio;
        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'guardar', 'multireqtoken' => $this->formToken(),
        ]);
        $this->runController(new Modelo425('Modelo425', '/Modelo425'));

        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'marcar-presentado', 'fechapresentacion' => '2091-01-20',
            'multireqtoken' => $this->formToken(),
        ]);
        $this->runController($this->controladorSoloLectura());

        $this->assertSame('borrador', DeclaracionIGIC::findWhere([Where::eq('tipo', '425')])->estado);
    }

    public function testLasFacturasSeLeenUnaVezPorCarga(): void
    {
        $this->makeTrimestre();
        $this->request(['codejercicio' => $this->ejercicio()->codejercicio]);
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
        $controller = new class ('Modelo425', '/Modelo425') extends Modelo425 {
            public IGICHelper $helperPruebas;

            protected function nuevoHelper(): IGICHelper
            {
                return $this->helperPruebas;
            }
        };
        $controller->helperPruebas = $helper;
        $this->runController($controller);

        $this->assertSame(2, $helper->lecturas);
    }

    public function testEditarNoCambiaElEstado(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');
        $declaracion = RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva);
        $this->assertTrue($declaracion->marcarPresentado('REF-1', '2090-04-15'));

        // devolverla a borrador desde la ficha permitiría borrar una declaración presentada
        // el formulario envía todos los campos, también los de solo lectura
        $this->request(array_merge($declaracion->toArray(), [
            'action' => 'edit', 'code' => $declaracion->idmodelo, 'estado' => 'borrador',
            'numeroreferencia' => 'REF-2', 'fechapresentacion' => '2090-04-16',
        ]), ['code' => $declaracion->idmodelo]);
        $controller = new class ('EditDeclaracionIGIC') extends EditDeclaracionIGIC {
            // la semilla del token del núcleo cambia en cada ejecución
            public function validateFormToken(): bool
            {
                return true;
            }
        };
        $this->runController($controller);

        $this->assertTrue($declaracion->reload());
        $this->assertSame('presentado', $declaracion->estado);
        $this->assertSame('REF-2', $declaracion->numeroreferencia, $this->recentLog());
    }

    public function testSoloLecturaNoMuestraLasAcciones(): void
    {
        $this->makeTrimestre();
        $codejercicio = $this->ejercicio()->codejercicio;
        $this->request(['codejercicio' => $codejercicio]);
        $html = $this->runController($this->controladorSoloLectura());
        $this->assertStringNotContainsString('name="proceso" value="guardar"', $html);

        $this->request([
            'codejercicio' => $codejercicio, 'proceso' => 'guardar', 'multireqtoken' => $this->formToken(),
        ]);
        $this->runController(new Modelo425('Modelo425', '/Modelo425'));
        $this->request(['codejercicio' => $codejercicio]);
        $html = $this->runController($this->controladorSoloLectura());
        $this->assertStringNotContainsString('data-bs-target="#modalMarcarPresentado425"', $html);
    }

    public function testListadoDeDeclaraciones(): void
    {
        $controller = new ListDeclaracionIGIC('ListDeclaracionIGIC');
        $this->runController($controller);

        $this->assertArrayHasKey('ListDeclaracionIGIC', $controller->views);
        $this->assertNotFalse($controller->getTemplate());
    }

    public function testEditarYDescargarDeclaracion(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');
        $declaracion = RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva);

        // ficha con las pestañas de facturas
        $this->request([], ['code' => $declaracion->idmodelo]);
        $controller = new EditDeclaracionIGIC('EditDeclaracionIGIC');
        $this->runController($controller);
        $this->assertTrue($controller->views['EditDeclaracionIGIC']->model->exists());
        $this->assertCount(1, $controller->views['ListDeclaracionIGICFactura-cliente']->cursor);
        $this->assertCount(1, $controller->views['ListDeclaracionIGICFactura-proveedor']->cursor);

        // el fichero lleva al Modelo 420 de la declaración
        $this->request(['action' => 'download-atc'], ['code' => $declaracion->idmodelo]);
        $controller = new EditDeclaracionIGIC('EditDeclaracionIGIC');
        $this->runController($controller);
        $refresh = $this->ultimaRespuesta->headers->get('Refresh');
        $this->assertStringContainsString('Modelo420?id=' . $regiva->idregiva, $refresh);

        // el 425 no tiene fichero
        $declaracion->tipo = '425';
        $declaracion->periodo = 'ANUAL';
        $this->assertTrue($declaracion->save());
        $this->request(['action' => 'download-atc'], ['code' => $declaracion->idmodelo]);
        $this->runController(new EditDeclaracionIGIC('EditDeclaracionIGIC'));
        $this->assertStringContainsString(Tools::lang()->trans('fichero-atc-solo-420'), $this->recentLog());

        // descarga de una declaración inexistente
        $this->request(['action' => 'download-atc'], ['code' => 999999]);
        $this->runController(new EditDeclaracionIGIC('EditDeclaracionIGIC'));
        $this->assertStringContainsString(Tools::lang()->trans('record-not-found'), $this->recentLog());
    }

    private function contar425(string $codejercicio): int
    {
        return DeclaracionIGIC::count([Where::eq('tipo', '425'), Where::eq('codejercicio', $codejercicio)]);
    }

    /**
     * Controlador de un usuario con permiso de solo lectura.
     */
    private function controladorSoloLectura(): Modelo425
    {
        return new class ('Modelo425', '/Modelo425') extends Modelo425 {
            protected function execAction(string $action): void
            {
                $this->allowUpdate = false;
                parent::execAction($action);
            }
        };
    }

    private function request(array $data, array $query = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = $query;
        $_POST = $data;
        $_REQUEST = array_merge($query, $data);
    }
}
