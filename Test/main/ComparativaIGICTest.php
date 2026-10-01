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

use FacturaScripts\Plugins\ModelosIGIC\Lib\ComparativaIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use PHPUnit\Framework\TestCase;

final class ComparativaIGICTest extends TestCase
{
    public function testModelo420AgrupaPorEjercicioYTrimestreSinInventarPeriodos(): void
    {
        $rows = ComparativaIGIC::modelo420([
            $this->declaracion('420', '2024', 'T1', 100.0, 40.0),
            $this->declaracion('420', '2024', 'T3', 150.0, 60.0),
            $this->declaracion('420', '2025', 'T1', 120.0, 50.0),
        ], 2024, 2025);

        $this->assertCount(2, $rows);
        $this->assertSame(2024, $rows[0]['ejercicio']);
        $this->assertEqualsWithDelta(60.0, $rows[0]['periodos']['T1']['resultado'], 0.001);
        $this->assertNull($rows[0]['periodos']['T2']);
        $this->assertEqualsWithDelta(90.0, $rows[0]['periodos']['T3']['resultado'], 0.001);
        $this->assertNull($rows[0]['periodos']['T4']);
        $this->assertSame(2025, $rows[1]['ejercicio']);
    }

    public function testModelo425FiltraRangoYOrdenaPorEjercicio(): void
    {
        $rows = ComparativaIGIC::modelo425([
            $this->declaracion('425', '2023', 'ANUAL', 200.0, 80.0),
            $this->declaracion('425', '2024', 'ANUAL', 250.0, 90.0),
            $this->declaracion('425', '2025', 'ANUAL', 300.0, 110.0),
            $this->declaracion('420', '2025', 'T1', 75.0, 20.0),
        ], 2024, 2025);

        $this->assertCount(2, $rows);
        $this->assertSame(2024, $rows[0]['ejercicio']);
        $this->assertEqualsWithDelta(250.0, $rows[0]['totaldevengado'], 0.001);
        $this->assertEqualsWithDelta(90.0, $rows[0]['totaldeducible'], 0.001);
        $this->assertEqualsWithDelta(160.0, $rows[0]['resultado'], 0.001);
        $this->assertSame(2025, $rows[1]['ejercicio']);
    }

    public function testRangoInvertidoSeNormaliza(): void
    {
        $rows = ComparativaIGIC::modelo425([
            $this->declaracion('425', '2024', 'ANUAL', 250.0, 90.0),
            $this->declaracion('425', '2025', 'ANUAL', 300.0, 110.0),
        ], 2025, 2024);

        $this->assertCount(2, $rows);
        $this->assertSame(2024, $rows[0]['ejercicio']);
        $this->assertSame(2025, $rows[1]['ejercicio']);
    }

    private function declaracion(
        string $tipo,
        string $ejercicio,
        string $periodo,
        float $devengado,
        float $deducible
    ): DeclaracionIGIC {
        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = $tipo;
        $declaracion->codejercicio = $ejercicio;
        $declaracion->periodo = $periodo;
        $declaracion->fechainicio = $ejercicio . '-01-01';
        $declaracion->fechafin = $ejercicio . '-12-31';
        $declaracion->totaldevengado = $devengado;
        $declaracion->totaldeducible = $deducible;
        $declaracion->resultado = $devengado - $deducible;

        return $declaracion;
    }
}
