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
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\ModelosIGIC\Controller\Modelo420;
use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
use FacturaScripts\Plugins\ModelosIGIC\Lib\RegularizacionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use PHPUnit\Framework\TestCase;

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
        $this->post(['proceso' => 'comprobar', 'desde' => '2190-01-01', 'hasta' => '2190-03-31']);

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertSame([], $controller->auxRegiva);
    }

    public function testCalcularSinDatos(): void
    {
        $this->ejercicio();
        $this->post(['proceso' => 'comprobar', 'desde' => '2090-10-01', 'hasta' => '2090-12-31']);

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

        // descargar el fichero
        $this->get(['id' => $idregiva, 'download-atc' => '1']);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $contenido = $this->runController($controller);
        $xml = simplexml_load_string(ATCFileGenerator::decode($contenido));
        $this->assertSame('70.00', (string) $xml->IVA_DEVENGADO->TOTAL_CUOTA);
    }

    public function testGuardarSinTokenNoHaceNada(): void
    {
        $this->makeTrimestre();
        $this->post(['proceso' => 'guardar', 'desde' => '2090-01-01', 'hasta' => '2090-03-31']);

        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);

        $this->assertNull($controller->selectedRegiva);
        $this->assertSame(0, $this->contarRegularizaciones());
    }

    public function testGuardarSinEjercicioAbierto(): void
    {
        $this->post([
            'proceso' => 'guardar', 'desde' => '2190-01-01', 'hasta' => '2190-03-31',
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
            'proceso' => 'guardar', 'desde' => '2090-10-01', 'hasta' => '2090-12-31',
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
