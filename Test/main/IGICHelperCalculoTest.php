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
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use PHPUnit\Framework\TestCase;

/**
 * Cálculos del IGICHelper con facturas y asientos reales.
 */
final class IGICHelperCalculoTest extends TestCase
{
    use ModelosIGICFixtures;

    protected function tearDown(): void
    {
        $this->cleanFixtures();
    }

    public function testDesgloseAgrupaFacturasPorTipo(): void
    {
        $this->makeTrimestre();
        $this->makeFacturaCliente('15-03-' . static::$year, 500.0);
        $this->makeFacturaCliente('16-03-' . static::$year, 100.0, 3.0);

        $helper = new IGICHelper();
        $ventas = $helper->desgloseIGICVentas('2090-01-01', '2090-03-31');
        $this->assertCount(2, $ventas);
        $this->assertEquals(3.0, $ventas[0]['iva']);
        $this->assertEqualsWithDelta(100.0, $ventas[0]['neto'], 0.001);
        $this->assertEqualsWithDelta(3.0, $ventas[0]['totaliva'], 0.001);
        $this->assertEquals(7.0, $ventas[1]['iva']);
        $this->assertEqualsWithDelta(1500.0, $ventas[1]['neto'], 0.001);
        $this->assertEqualsWithDelta(105.0, $ventas[1]['totaliva'], 0.001);
        $this->assertEqualsWithDelta(108.0, $helper->calcularTotalDevengado($ventas), 0.001);

        $compras = $helper->desgloseIGICCompras('2090-01-01', '2090-03-31');
        $this->assertCount(1, $compras);
        $this->assertEqualsWithDelta(400.0, $compras[0]['neto'], 0.001);
        $this->assertEqualsWithDelta(28.0, $helper->calcularTotalDeducible($compras), 0.001);
    }

    public function testDesgloseFiltraPorFechasYEmpresa(): void
    {
        $this->makeTrimestre();
        $helper = new IGICHelper();
        $idempresa = (int) Empresas::default()->idempresa;

        $this->assertCount(1, $helper->desgloseIGICVentas('2090-01-01', '2090-03-31', $idempresa));
        $this->assertSame([], $helper->desgloseIGICVentas('2090-04-01', '2090-06-30', $idempresa));
        $this->assertSame([], $helper->desgloseIGICVentas('2090-01-01', '2090-03-31', $idempresa + 1000));
        $this->assertSame([], $helper->desgloseIGICCompras('2090-01-01', '2090-03-31', $idempresa + 1000));
    }

    public function testHayFacturasSinAsiento(): void
    {
        $this->makeTrimestre();
        $helper = new IGICHelper();

        $this->assertFalse($helper->hayFacturasSinAsiento('2090-01-01', '2090-03-31'));
    }

    public function testCalcularRegularizacionAIngresar(): void
    {
        $this->makeTrimestre();
        $ejercicio = $this->ejercicio();

        $partidas = (new IGICHelper())->calcularRegularizacion('2090-01-01', '2090-03-31', $ejercicio->codejercicio);
        $this->assertCount(3, $partidas);
        $cierre = end($partidas);
        $this->assertEqualsWithDelta(42.0, $cierre['haber'], 0.001);
        $this->assertEquals(0, $cierre['debe']);
    }

    public function testCalcularRegularizacionADevolver(): void
    {
        $this->ejercicio();
        $this->makeFacturaCliente('10-05-' . static::$year, 100.0);
        $this->makeFacturaProveedor('11-05-' . static::$year, 1000.0);
        $ejercicio = $this->ejercicio();

        $partidas = (new IGICHelper())->calcularRegularizacion('2090-04-01', '2090-06-30', $ejercicio->codejercicio);
        $cierre = end($partidas);
        $this->assertEqualsWithDelta(63.0, $cierre['debe'], 0.001);
        $this->assertEquals(0, $cierre['haber']);
    }

    public function testCalcularRegularizacionSinMovimientos(): void
    {
        $ejercicio = $this->ejercicio();

        $helper = new IGICHelper();
        $this->assertSame([], $helper->calcularRegularizacion('2090-10-01', '2090-12-31', $ejercicio->codejercicio));
    }

    public function testSubcuentasEspeciales(): void
    {
        $ejercicio = $this->ejercicio();
        $helper = new IGICHelper();

        $this->assertNotEmpty($helper->getSubcuentasEspeciales('IVAREP', $ejercicio->codejercicio));
        $this->assertNotNull($helper->getSubcuentaEspecial('IVAACR', $ejercicio->codejercicio));
        $this->assertNull($helper->getSubcuentaEspecial('NOEXISTE', $ejercicio->codejercicio));
    }

    public function testTotalesSubcuentaSinMovimientos(): void
    {
        $totales = (new IGICHelper())->getTotalesSubcuenta(-1, '2090-01-01', '2090-12-31');

        $this->assertSame(['debe' => 0.0, 'haber' => 0.0, 'saldo' => 0.0], $totales);
    }

    public function testPeriodoActualYFechasPorPeriodo(): void
    {
        $helper = new IGICHelper();

        $actual = $helper->calcularPeriodoActual();
        $this->assertMatchesRegularExpression('/^T[1-4]$/', $actual['periodo']);
        $this->assertLessThan($actual['fecha_hasta'], $actual['fecha_desde']);

        $this->assertSame(
            ['fecha_desde' => '2090-01-01', 'fecha_hasta' => '2090-03-31'],
            $helper->fechasPorPeriodo('T1', 2090)
        );
        $this->assertSame(
            ['fecha_desde' => '2090-04-01', 'fecha_hasta' => '2090-06-30'],
            $helper->fechasPorPeriodo('T2', 2090)
        );
        $this->assertSame(
            ['fecha_desde' => '2090-07-01', 'fecha_hasta' => '2090-09-30'],
            $helper->fechasPorPeriodo('T3', 2090)
        );
        $this->assertSame(
            ['fecha_desde' => '2090-10-01', 'fecha_hasta' => '2090-12-31'],
            $helper->fechasPorPeriodo('T4', 2090)
        );
        $this->assertSame(
            ['fecha_desde' => '2090-01-01', 'fecha_hasta' => '2090-12-31'],
            $helper->fechasPorPeriodo('ANUAL', 2090)
        );
    }

    public function testNombreTipoIGIC(): void
    {
        $helper = new IGICHelper();

        foreach ([0, 3, 7, 9.5, 15, 20] as $tipo) {
            $this->assertNotSame('', $helper->nombreTipoIGIC($tipo));
        }
        $this->assertSame('42%', $helper->nombreTipoIGIC(42));
    }
}
