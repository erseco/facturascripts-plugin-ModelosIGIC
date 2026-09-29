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

use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGICFactura;
use PHPUnit\Framework\TestCase;

/**
 * Modelos DeclaracionIGIC y DeclaracionIGICFactura contra la base de datos.
 */
final class DeclaracionIGICTest extends TestCase
{
    use ModelosIGICFixtures;

    protected function tearDown(): void
    {
        $this->cleanFixtures();
    }

    public function testValidaciones(): void
    {
        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = '303';
        $declaracion->periodo = 'T1';
        $declaracion->codejercicio = $this->ejercicio()->codejercicio;
        $this->assertFalse($declaracion->test());

        $declaracion->tipo = '420';
        $declaracion->periodo = '';
        $this->assertFalse($declaracion->test());

        $declaracion->periodo = 'T1';
        $declaracion->codejercicio = '';
        $this->assertFalse($declaracion->test());
    }

    public function testEstados(): void
    {
        $declaracion = new DeclaracionIGIC();
        $this->assertSame('borrador', $declaracion->estado);
        $this->assertSame('warning', $declaracion->estadoClase());

        $esperado = ['presentado' => 'success', 'rectificado' => 'secondary', 'otro' => 'primary'];
        foreach ($esperado as $estado => $clase) {
            $declaracion->estado = $estado;
            $this->assertSame($clase, $declaracion->estadoClase());
            $this->assertNotSame('', $declaracion->estadoDescripcion());
        }
        $this->assertSame('otro', $declaracion->estadoDescripcion());
        $this->assertStringContainsString('ListDeclaracionIGIC', $declaracion->url('list'));
    }

    public function testGuardarFacturasYBorrarLasElimina(): void
    {
        $this->makeTrimestre();
        $declaracion = $this->makeDeclaracion();

        $this->assertTrue($declaracion->guardarFacturas((int) Empresas::default()->idempresa));
        $this->assertCount(2, $declaracion->getFacturas());
        $this->assertCount(1, $declaracion->getFacturasCliente());
        $this->assertCount(1, $declaracion->getFacturasProveedor());
        $this->assertSame((int) Empresas::default()->idempresa, $declaracion->getIdEmpresa());

        $idmodelo = $declaracion->idmodelo;
        $this->assertTrue($declaracion->delete());
        $this->assertSame(0, DeclaracionIGICFactura::count([\FacturaScripts\Core\Where::eq('idmodelo', $idmodelo)]));
    }

    public function testRectificativo(): void
    {
        $this->makeTrimestre();
        $declaracion = $this->makeDeclaracion();
        $declaracion->guardarFacturas();

        // solo se puede rectificar una declaración presentada
        $this->assertNull($declaracion->crearRectificativo());

        $this->assertTrue($declaracion->marcarPresentado('ATC-123', '2090-04-18'));
        $this->assertSame('ATC-123', $declaracion->numeroreferencia);

        $nuevo = $declaracion->crearRectificativo();
        $this->assertNotNull($nuevo);
        $this->assertSame('rectificado', $declaracion->estado);
        $this->assertSame('borrador', $nuevo->estado);
        $this->assertTrue($nuevo->esRectificativo());
        $this->assertFalse($declaracion->esRectificativo());
        $this->assertEquals($declaracion->idmodelo, $nuevo->getModeloRectificado()->idmodelo);
        $this->assertNull($declaracion->getModeloRectificado());
        $this->assertCount(1, $declaracion->getModelosRectificativos());
        $this->assertCount(2, $nuevo->getFacturas());
    }

    public function testRectificativoConFalloDeshaceTodo(): void
    {
        $declaracion = $this->makeDeclaracion();
        $this->assertTrue($declaracion->marcarPresentado());

        // una factura inválida hace fallar la copia
        $factura = new DeclaracionIGICFactura();
        $factura->idmodelo = $declaracion->idmodelo;
        $factura->tipofactura = 'cliente';
        $factura->idfactura = 1;
        $this->assertTrue($factura->save());
        self::db()->exec('UPDATE declaraciones_igic_facturas SET tipofactura = ' . self::db()->var2str('otro')
            . ' WHERE id = ' . (int) $factura->id);

        $this->assertNull($declaracion->crearRectificativo());
        $this->assertTrue($declaracion->reload());
        $this->assertSame('presentado', $declaracion->estado);
        $this->assertSame([], $declaracion->getModelosRectificativos());
    }

    public function testFacturaDesdeFacturas(): void
    {
        $this->ejercicio();
        $venta = $this->makeFacturaCliente('10-02-' . static::$year, 100.0);
        $compra = $this->makeFacturaProveedor('11-02-' . static::$year, 50.0);
        $declaracion = $this->makeDeclaracion();

        $linea = DeclaracionIGICFactura::fromFacturaCliente($venta, (int) $declaracion->idmodelo);
        $this->assertTrue($linea->save());
        $this->assertEqualsWithDelta(107.0, $linea->total(), 0.001);
        $this->assertEquals($venta->idfactura, $linea->getFactura()->idfactura);
        $this->assertEquals($declaracion->idmodelo, $linea->getModelo()->idmodelo);
        $this->assertStringContainsString('EditFacturaCliente', $linea->urlFactura());

        $linea = DeclaracionIGICFactura::fromFacturaProveedor($compra, (int) $declaracion->idmodelo);
        $this->assertTrue($linea->save());
        $this->assertStringContainsString('EditFacturaProveedor', $linea->urlFactura());

        $linea->tipofactura = 'otro';
        $this->assertNull($linea->getFactura());
        $this->assertSame('#', $linea->urlFactura());
    }

    public function testFacturaValidaciones(): void
    {
        $linea = new DeclaracionIGICFactura();
        $this->assertFalse($linea->test(), 'Sin modelo');

        $linea->idmodelo = 1;
        $linea->tipofactura = 'otro';
        $this->assertFalse($linea->test(), 'Tipo inválido');

        $linea->tipofactura = 'cliente';
        $this->assertFalse($linea->test(), 'Sin factura');

        $linea->idmodelo = -1;
        $this->assertNull($linea->getModelo());
    }

    private function makeDeclaracion(): DeclaracionIGIC
    {
        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = '420';
        $declaracion->periodo = 'T1';
        $declaracion->codejercicio = $this->ejercicio()->codejercicio;
        $declaracion->fechainicio = '2090-01-01';
        $declaracion->fechafin = '2090-03-31';
        $this->assertTrue($declaracion->save());

        return $declaracion;
    }

    private static function db(): \FacturaScripts\Core\Base\DataBase
    {
        return new \FacturaScripts\Core\Base\DataBase();
    }
}
