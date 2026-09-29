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

use FacturaScripts\Core\Lib\OperacionIVA;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ModelosIGIC\Controller\Modelo420;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use FacturaScripts\Plugins\ModelosIGIC\Lib\RegularizacionIGIC;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Una prueba por cada regla normativa del plugin. Cada prueba cita su fuente; el detalle y
 * los enlaces están en doc/NORMATIVA.md.
 */
final class NormativaIGICTest extends TestCase
{
    use ModelosIGICFixtures;

    protected function tearDown(): void
    {
        $this->cleanFixtures();
    }

    /**
     * Tipos vigentes desde el 01/01/2026: TR IGIC (DL 1/2025) art. 32.1, en la redacción de la
     * Ley 9/2025 (BOC n.º 256 de 29/12/2025).
     */
    public function testTiposVigentesDesde2026(): void
    {
        $helper = new IGICHelper();
        $esperados = [
            '0' => 'igic-tipo-cero',
            '1' => 'igic-tipo-especifico',
            '3' => 'igic-tipo-superreducido',
            '5' => 'igic-tipo-reducido',
            '7' => 'igic-tipo-general',
            '9.5' => 'igic-tipo-incrementado',
            '15' => 'igic-tipo-incrementado',
            '20' => 'igic-tipo-especial',
        ];

        foreach ($esperados as $tipo => $clave) {
            $nombre = $helper->nombreTipoIGIC((float) $tipo, '2026-03-31');
            $this->assertStringStartsWith(Tools::lang()->trans($clave) . ' (', $nombre, 'Tipo ' . $tipo);
            $this->assertTrue($helper->esTipoVigente((float) $tipo, '2026-03-31'), 'Tipo ' . $tipo);
        }
    }

    /**
     * El tipo específico del 1 % se crea con efectos desde el 01/01/2026 (Ley 9/2025,
     * disposición final novena; TR IGIC art. 33 bis).
     */
    public function testTipoEspecificoSoloDesde2026(): void
    {
        $helper = new IGICHelper();

        $this->assertSame('1 %', $helper->nombreTipoIGIC(1, '2025-12-31'));
        $this->assertFalse($helper->esTipoVigente(1, '2025-12-31'));
        $this->assertStringStartsWith(
            Tools::lang()->trans('igic-tipo-especifico'),
            $helper->nombreTipoIGIC(1, IGICHelper::FECHA_TIPO_ESPECIFICO)
        );
    }

    /**
     * Desde la entrada en vigor del DL 1/2025 (21/10/2025, disposición final única) el 3 % es
     * «superreducido» y el 5 % «reducido» (exposición de motivos del DL 1/2025).
     */
    public function testDenominacionesDelTextoRefundido(): void
    {
        $helper = new IGICHelper();

        $this->assertStringStartsWith(
            Tools::lang()->trans('igic-tipo-superreducido'),
            $helper->nombreTipoIGIC(3, IGICHelper::FECHA_TEXTO_REFUNDIDO)
        );
        $this->assertStringStartsWith(
            Tools::lang()->trans('igic-tipo-reducido'),
            $helper->nombreTipoIGIC(5, '2025-11-15')
        );
    }

    /**
     * Antes del 21/10/2025 regía la Ley 4/2012: el plugin no documenta sus denominaciones y solo
     * muestra el porcentaje (doc/NORMATIVA.md, pendiente 8).
     */
    public function testAntesDelTextoRefundidoSoloPorcentaje(): void
    {
        $helper = new IGICHelper();

        $this->assertSame('3 %', $helper->nombreTipoIGIC(3, '2025-10-20'));
        $this->assertNull($helper->esTipoVigente(3, '2025-10-20'));
    }

    /**
     * Los tipos 6,5 % y 13,5 % del núcleo no figuran en el art. 32.1 del TR IGIC vigente.
     */
    public function testTiposDelNucleoNoPrevistos(): void
    {
        $helper = new IGICHelper();

        $this->assertFalse($helper->esTipoVigente(6.5, '2026-03-31'));
        $this->assertFalse($helper->esTipoVigente(13.5, '2026-03-31'));
        $this->assertSame('6,5 %', $helper->nombreTipoIGIC(6.5, '2026-03-31'));
    }

    /**
     * Plazos: veinte primeros días naturales del mes siguiente; el último trimestre, durante
     * enero (Decreto 268/2011, art. 57.6). El 425 va con el 4T (art. 57.8).
     */
    public function testPlazosDePresentacion(): void
    {
        $helper = new IGICHelper();

        $this->assertSame(['desde' => '2026-04-01', 'hasta' => '2026-04-20'], $helper->plazoPresentacion('T1', 2026));
        $this->assertSame(['desde' => '2026-07-01', 'hasta' => '2026-07-20'], $helper->plazoPresentacion('T2', 2026));
        $this->assertSame(['desde' => '2026-10-01', 'hasta' => '2026-10-20'], $helper->plazoPresentacion('T3', 2026));
        $this->assertSame(['desde' => '2027-01-01', 'hasta' => '2027-01-31'], $helper->plazoPresentacion('T4', 2026));
        $this->assertSame(
            $helper->plazoPresentacion('T4', 2026),
            $helper->plazoPresentacion('ANUAL', 2026)
        );

        $this->expectException(InvalidArgumentException::class);
        $helper->plazoPresentacion('M01', 2026);
    }

    /**
     * El período de liquidación del 420 es el trimestre natural (Decreto 268/2011, art. 57.5):
     * el controlador ignora períodos y fechas que no sean de un trimestre.
     */
    public function testModelo420SoloAdmiteTrimestres(): void
    {
        $this->login();
        $this->assertSame(['T1', 'T2', 'T3', 'T4'], IGICHelper::PERIODOS_420);

        $this->post(['periodo' => 'T2', 'anyo' => '2090', 'desde' => '2090-05-10', 'hasta' => '2090-05-20']);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);
        $this->assertSame('T2', $controller->periodo);
        $this->assertSame('2090-04-01', $controller->fechaDesde);
        $this->assertSame('2090-06-30', $controller->fechaHasta);

        $this->post(['periodo' => 'M05', 'desde' => '2090-05-01', 'hasta' => '2090-05-31']);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);
        $this->assertContains($controller->periodo, IGICHelper::PERIODOS_420);
        $fechas = (new IGICHelper())->fechasPorPeriodo($controller->periodo, 2090);
        $this->assertSame($fechas['fecha_desde'], $controller->fechaDesde);
        $this->assertSame($fechas['fecha_hasta'], $controller->fechaHasta);
    }

    /**
     * Las operaciones se imputan al período de su devengo (Ley 20/1991, art. 18; instrucciones
     * del 420, apdos. 7 y 9): cuenta la fecha de devengo de la factura y, si no la tiene, su fecha.
     */
    public function testImputacionPorFechaDeDevengo(): void
    {
        $this->ejercicio();
        $this->makeFacturaClienteLineas('15-02-2090', [['base' => 200.0, 'tipo' => 7.0]]);
        $this->makeFacturaClienteLineas('02-04-2090', [['base' => 100.0, 'tipo' => 7.0]], '30-03-2090');
        $this->makeFacturaClienteLineas('05-04-2090', [['base' => 50.0, 'tipo' => 7.0]]);

        $helper = new IGICHelper();
        $t1 = $helper->desgloseIGICVentas('2090-01-01', '2090-03-31');
        $t2 = $helper->desgloseIGICVentas('2090-04-01', '2090-06-30');

        $this->assertEqualsWithDelta(300.0, $t1[0]['neto'], 0.001);
        $this->assertEqualsWithDelta(50.0, $t2[0]['neto'], 0.001);
    }

    /**
     * Solo entran en las casillas 01–18 las líneas de IGIC sin causa de exención (instrucciones
     * del 420, apdo. 7). Las demás se informan aparte; los suplidos no forman parte de la base.
     */
    public function testSoloLineasDeIGIC(): void
    {
        $this->ejercicio();
        $this->makeFacturaClienteLineas('10-02-2090', [
            ['base' => 100.0, 'tipo' => 7.0],
            ['base' => 50.0, 'tipo' => 21.0, 'operacion' => OperacionIVA::ES_OPERATION_01],
            ['base' => 30.0, 'tipo' => 0.0, 'excepcioniva' => 'ES_OTHER'],
            ['base' => 20.0, 'tipo' => 7.0, 'suplido' => true],
        ]);

        $helper = new IGICHelper();
        $desglose = $helper->desgloseIGICVentas('2090-01-01', '2090-03-31');
        $this->assertCount(1, $desglose);
        $this->assertEqualsWithDelta(100.0, $desglose[0]['neto'], 0.001);
        $this->assertEqualsWithDelta(7.0, $helper->calcularTotalDevengado($desglose), 0.001);

        $excluidas = $helper->excluidasVentas('2090-01-01', '2090-03-31');
        $this->assertCount(2, $excluidas);
        $this->assertSame('excepcion', $excluidas[0]['motivo']);
        $this->assertSame('ES_OTHER', $excluidas[0]['codigo']);
        $this->assertEqualsWithDelta(30.0, $excluidas[0]['neto'], 0.001);
        $this->assertSame('impuesto', $excluidas[1]['motivo']);
        $this->assertEqualsWithDelta(50.0, $excluidas[1]['neto'], 0.001);
        $this->assertSame([], $helper->excluidasCompras('2090-01-01', '2090-03-31'));
    }

    /**
     * La pantalla del 420 muestra aparte las líneas que no entran en el cálculo, para que el
     * usuario revise las casillas 46 y 47 (instrucciones del 420, apdo. 8).
     */
    public function testVistaMuestraLineasExcluidas(): void
    {
        $this->login();
        $this->ejercicio();
        $this->makeFacturaClienteLineas('10-02-2090', [
            ['base' => 100.0, 'tipo' => 7.0],
            ['base' => 50.0, 'tipo' => 21.0, 'operacion' => OperacionIVA::ES_OPERATION_01],
        ]);
        $this->makeFacturaProveedor('12-02-2090', 40.0);
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');
        $this->assertNotNull($regiva, $this->recentLog());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $_REQUEST = ['id' => $regiva->idregiva];
        $_POST = [];
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);

        $this->assertCount(1, $controller->excluidasVentas());
        $this->assertStringContainsString(Tools::lang()->trans('lineas-excluidas'), $html);
        $this->assertStringContainsString(Tools::lang()->trans('excluida-impuesto'), $html);
        $this->assertStringContainsString(Tools::lang()->trans('casillas-no-calculadas'), $html);
    }

    /**
     * El recargo no se suma al IGIC devengado ni deducible: el recargo de comerciantes minoristas
     * grava las importaciones y se liquida con ellas (TR IGIC art. 70.Uno.a).
     */
    public function testRecargoNoSeSumaAlIGIC(): void
    {
        $helper = new IGICHelper();
        $desglose = [
            ['iva' => 7.0, 'recargo' => 0.7, 'neto' => 1000.0, 'totaliva' => 70.0, 'totalrecargo' => 7.0],
            ['iva' => 3.0, 'recargo' => 0.0, 'neto' => 100.0, 'totaliva' => 3.0, 'totalrecargo' => 0.0],
        ];

        $this->assertEqualsWithDelta(73.0, $helper->calcularTotalDevengado($desglose), 0.001);
        $this->assertEqualsWithDelta(73.0, $helper->calcularTotalDeducible($desglose), 0.001);
        $this->assertEqualsWithDelta(7.0, $helper->calcularTotalRecargo($desglose), 0.001);
    }

    private function post(array $data): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];
        $_POST = $data;
        $_REQUEST = $data;
    }
}
