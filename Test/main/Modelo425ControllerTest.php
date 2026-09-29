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

use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\ModelosIGIC\Controller\EditDeclaracionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Controller\ListDeclaracionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Controller\Modelo425;
use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
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
            'multireqtoken' => $this->formToken(),
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
            public function totalDevengado(): float
            {
                // un importe no numérico hace fallar el guardado en la base de datos
                return NAN;
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

        // descarga del fichero
        $this->request(['action' => 'download-atc'], ['code' => $declaracion->idmodelo]);
        $controller = new EditDeclaracionIGIC('EditDeclaracionIGIC');
        $contenido = $this->runController($controller);
        $xml = simplexml_load_string(ATCFileGenerator::decode($contenido));
        $this->assertSame('420', (string) $xml->CABECERA->MODELO);

        // descarga de una declaración inexistente
        $this->request(['action' => 'download-atc'], ['code' => 999999]);
        $contenido = $this->runController(new EditDeclaracionIGIC('EditDeclaracionIGIC'));
        $this->assertStringNotContainsString('begin', $contenido);
    }

    private function contar425(string $codejercicio): int
    {
        return DeclaracionIGIC::count([Where::eq('tipo', '425'), Where::eq('codejercicio', $codejercicio)]);
    }

    private function request(array $data, array $query = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = $query;
        $_POST = $data;
        $_REQUEST = array_merge($query, $data);
    }
}
