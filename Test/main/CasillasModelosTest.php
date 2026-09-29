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

use FacturaScripts\Plugins\ModelosIGIC\Lib\CasillasModelo420;
use FacturaScripts\Plugins\ModelosIGIC\Lib\CasillasModelo425;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use PHPUnit\Framework\TestCase;

/**
 * Casillas de los modelos 420 y 425 según sus instrucciones oficiales (doc/NORMATIVA.md).
 */
final class CasillasModelosTest extends TestCase
{
    /**
     * Casillas 01–18: base, tipo y cuota de cada tipo aplicado, incluido el tipo cero
     * (instrucciones del 420, apdo. 7).
     */
    public function testFilasDevengado420(): void
    {
        $filas = (new CasillasModelo420())->filasDevengado([
            $this->item(7, 1000, 70),
            $this->item(0, 200, 0),
            $this->item(3, 100, 3),
        ], '2026-03-31');

        $this->assertCount(3, $filas);
        $this->assertSame(['01', '02', '03'], $filas[0]['casillas']);
        $this->assertSame(0.0, $filas[0]['tipo']);
        $this->assertSame(['04', '05', '06'], $filas[1]['casillas']);
        $this->assertSame(3.0, $filas[1]['tipo']);
        $this->assertSame(['07', '08', '09'], $filas[2]['casillas']);
        $this->assertEqualsWithDelta(1000.0, $filas[2]['base'], 0.001);
        $this->assertEqualsWithDelta(70.0, $filas[2]['cuota'], 0.001);
        $this->assertTrue($filas[2]['vigente']);
        $this->assertFalse($filas[2]['programa2026']);
    }

    /**
     * Las filas 16b–18c solo figuran en el programa de ayuda 2026 (doc/NORMATIVA.md, pendiente 1);
     * si hay más tipos que filas, las sobrantes quedan sin casilla.
     */
    public function testFilasAdicionalesYSinCasilla420(): void
    {
        $desglose = [];
        foreach ([0, 1, 3, 5, 6.5, 7, 9.5, 13.5, 15, 20] as $tipo) {
            $desglose[] = $this->item($tipo, 100, $tipo);
        }

        $calculo = (new CasillasModelo420())->calcular($desglose, [], '2026-03-31');
        $filas = $calculo['filas'];

        $this->assertCount(10, $filas);
        $this->assertSame(['16', '17', '18'], $filas[5]['casillas']);
        $this->assertFalse($filas[5]['programa2026']);
        $this->assertSame(['16b', '17b', '18b'], $filas[6]['casillas']);
        $this->assertTrue($filas[6]['programa2026']);
        $this->assertSame(['16c', '17c', '18c'], $filas[7]['casillas']);
        $this->assertNull($filas[8]['casillas']);
        $this->assertSame(2, $calculo['sinCasilla']);
        $this->assertFalse($filas[4]['vigente'], '6,5 % no figura en el art. 32.1 del TR IGIC');
    }

    /**
     * Casillas 25 (03+…+18+20+22−24), 26–27 (interiores corrientes), 40 (27+29+…+39),
     * 41 (25−40) y 45 (41+42−43−44), según las instrucciones del 420, apdo. 7.
     */
    public function testLiquidacion420(): void
    {
        $calculo = (new CasillasModelo420())->calcular(
            [$this->item(7, 1000, 70), $this->item(3, 100, 3, 0.3)],
            [$this->item(7, 400, 28, 2.8)],
            '2026-03-31'
        );
        $casillas = $calculo['casillas'];

        $this->assertEqualsWithDelta(73.0, $casillas['25']['importe'], 0.001);
        $this->assertSame(CasillasModelo420::PARCIAL, $casillas['25']['estado']);
        $this->assertEqualsWithDelta(400.0, $casillas['26']['importe'], 0.001);
        $this->assertEqualsWithDelta(28.0, $casillas['27']['importe'], 0.001);
        $this->assertSame(CasillasModelo420::CALCULADA, $casillas['27']['estado']);
        $this->assertEqualsWithDelta(28.0, $casillas['40']['importe'], 0.001);
        $this->assertEqualsWithDelta(45.0, $casillas['41']['importe'], 0.001);
        $this->assertEqualsWithDelta(45.0, $casillas['45']['importe'], 0.001);
        $this->assertSame('I', $calculo['resultado']);
        $this->assertEqualsWithDelta(3.1, $calculo['recargo'], 0.001);
        $this->assertArrayHasKey('43', $calculo['noCalculadas']);
    }

    /**
     * Tipo de resultado (instrucciones del 420, apdos. 3–5): positivo a ingresar; negativo o
     * cero a compensar; sin actividad si no hay operaciones.
     */
    public function testTipoResultado420(): void
    {
        $casillas = new CasillasModelo420();

        $this->assertSame('I', $casillas->tipoResultado(10.0));
        $this->assertSame('C', $casillas->tipoResultado(-10.0));
        $this->assertSame('C', $casillas->tipoResultado(0.0));
        $this->assertSame('S', $casillas->tipoResultado(0.0, true));
        $this->assertSame('S', $casillas->calcular([], [], '2026-03-31')['resultado']);
        $this->assertSame('C', $casillas->calcular([], [$this->item(7, 100, 7)], '2026-03-31')['resultado']);
    }

    /**
     * Casillas del 425: 01–18 (régimen ordinario), 74, 79, 80–81, 94, 95, 113, 115, 116 y 120
     * (instrucciones del 425, apdos. 5, 7, 8 y 9).
     */
    public function testLiquidacion425(): void
    {
        $modelos = [
            $this->declaracion('420', 'presentado', 50.0),
            $this->declaracion('420', 'rectificado', 90.0),
            $this->declaracion('420', 'borrador', -20.0),
            $this->declaracion('425', 'borrador', 500.0),
        ];

        $calculo = (new CasillasModelo425())->calcular(
            [$this->item(7, 2000, 140), $this->item(0, 500, 0)],
            [$this->item(7, 800, 56)],
            '2026-12-31',
            $modelos
        );
        $casillas = $calculo['casillas'];

        $this->assertSame(['01', '02', '03'], $calculo['filas'][0]['casillas']);
        $this->assertSame(['04', '05', '06'], $calculo['filas'][1]['casillas']);
        $this->assertEqualsWithDelta(2500.0, $casillas['74']['importe'], 0.001);
        $this->assertEqualsWithDelta(140.0, $casillas['79']['importe'], 0.001);
        $this->assertEqualsWithDelta(800.0, $casillas['80']['importe'], 0.001);
        $this->assertEqualsWithDelta(56.0, $casillas['81']['importe'], 0.001);
        $this->assertEqualsWithDelta(56.0, $casillas['94']['importe'], 0.001);
        $this->assertEqualsWithDelta(84.0, $casillas['95']['importe'], 0.001);
        $this->assertEqualsWithDelta(84.0, $casillas['113']['importe'], 0.001);
        $this->assertEqualsWithDelta(84.0, $casillas['115']['importe'], 0.001);
        $this->assertEqualsWithDelta(50.0, $casillas['116']['importe'], 0.001);
        $this->assertEqualsWithDelta(2500.0, $casillas['120']['importe'], 0.001);
        $this->assertSame(0, $calculo['sinCasilla']);
    }

    /**
     * Las instrucciones del 425 citan «casillas de la 01 a 18 bis» sin numerar la fila bis
     * (doc/NORMATIVA.md, pendiente 6): a partir de la séptima fila no hay casilla.
     */
    public function testFilasSinCasilla425(): void
    {
        $desglose = [];
        foreach ([0, 1, 3, 5, 7, 9.5, 15] as $tipo) {
            $desglose[] = $this->item($tipo, 100, $tipo);
        }

        $calculo = (new CasillasModelo425())->calcular($desglose, [], '2026-12-31', []);

        $this->assertSame(['16', '17', '18'], $calculo['filas'][5]['casillas']);
        $this->assertNull($calculo['filas'][6]['casillas']);
        $this->assertFalse($calculo['filas'][6]['programa2026']);
        $this->assertSame(1, $calculo['sinCasilla']);
    }

    private function declaracion(string $tipo, string $estado, float $resultado): DeclaracionIGIC
    {
        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = $tipo;
        $declaracion->estado = $estado;
        $declaracion->resultado = $resultado;

        return $declaracion;
    }

    private function item(float $tipo, float $neto, float $cuota, float $recargo = 0.0): array
    {
        return [
            'iva' => $tipo,
            'recargo' => $recargo > 0 ? 1.0 : 0.0,
            'neto' => $neto,
            'totaliva' => $cuota,
            'totalrecargo' => $recargo,
        ];
    }
}
