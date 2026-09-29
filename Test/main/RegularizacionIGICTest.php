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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use FacturaScripts\Plugins\ModelosIGIC\Lib\RegularizacionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use PHPUnit\Framework\TestCase;

/**
 * Creación y borrado de regularizaciones del Modelo 420 contra la base de datos.
 */
final class RegularizacionIGICTest extends TestCase
{
    use ModelosIGICFixtures;

    protected function tearDown(): void
    {
        $this->cleanFixtures();
    }

    public function testGuardarCreaAsientoCuadradoRegularizacionYDeclaracion(): void
    {
        $this->makeTrimestre();
        $ejercicio = $this->ejercicio();

        $regiva = (new RegularizacionIGIC())->guardar($ejercicio, '2090-01-01', '2090-03-31', 'T1');
        $this->assertNotNull($regiva, $this->recentLog());
        $this->assertSame('T1', $regiva->periodo);

        // asiento cuadrado y bloqueado: 70 repercutido, 28 soportado, 42 a ingresar
        $asiento = new Asiento();
        $this->assertTrue($asiento->load($regiva->idasiento));
        $this->assertTrue($asiento->isBalanced());
        $this->assertFalse((bool) $asiento->editable);
        $this->assertEqualsWithDelta(70.0, $asiento->importe, 0.001);

        $partidas = RegularizacionIGIC::getPartidas($regiva);
        $this->assertCount(3, $partidas);
        $debe = array_sum(array_map(static fn ($p) => $p->debe, $partidas));
        $haber = array_sum(array_map(static fn ($p) => $p->haber, $partidas));
        $this->assertEqualsWithDelta(70.0, $debe, 0.001);
        $this->assertEqualsWithDelta(70.0, $haber, 0.001);

        $declaracion = RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva);
        $this->assertNotNull($declaracion);
        $this->assertSame('420', $declaracion->tipo);
        $this->assertSame('borrador', $declaracion->estado);
        $this->assertEqualsWithDelta(70.0, $declaracion->totaldevengado, 0.001);
        $this->assertEqualsWithDelta(28.0, $declaracion->totaldeducible, 0.001);
        $this->assertEqualsWithDelta(42.0, $declaracion->resultado, 0.001);
        $this->assertCount(1, $declaracion->getFacturasCliente());
        $this->assertCount(1, $declaracion->getFacturasProveedor());
    }

    public function testGuardarSinDatosNoCreaNada(): void
    {
        $ejercicio = $this->ejercicio();

        $this->assertNull((new RegularizacionIGIC())->guardar($ejercicio, '2090-07-01', '2090-09-30', 'T3'));
        $this->assertSame(0, $this->contarRegularizaciones());
    }

    public function testGuardarPeriodoSolapadoSeRechaza(): void
    {
        $this->makeTrimestre();
        $servicio = new RegularizacionIGIC();

        $this->assertNotNull($servicio->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1'));
        $this->assertNull($servicio->guardar($this->ejercicio(), '2090-02-01', '2090-02-28', 'T1'));
        $this->assertSame(1, $this->contarRegularizaciones());
    }

    public function testGuardarConFacturasSinAsientoSeRechaza(): void
    {
        $this->makeTrimestre();
        $helper = new class () extends IGICHelper {
            public function hayFacturasSinAsiento(string $fechaInicio, string $fechaFin, ?int $idempresa = null): bool
            {
                return true;
            }
        };

        $servicio = new RegularizacionIGIC($helper);
        $this->assertNull($servicio->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1'));
        $this->assertSame(0, $this->contarRegularizaciones());
    }

    public function testGuardarSinSubcuentaDeCierreSeRechaza(): void
    {
        $this->makeTrimestre();

        // sin la partida de cierre (IVAACR) el asiento no cuadra
        $helper = new class () extends IGICHelper {
            public function calcularRegularizacion(string $fechaInicio, string $fechaFin, string $codEjercicio): array
            {
                return array_slice(parent::calcularRegularizacion($fechaInicio, $fechaFin, $codEjercicio), 0, -1);
            }
        };
        $servicio = new RegularizacionIGIC($helper);
        $this->assertNull($servicio->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1'));

        // y sin la de IVADEU cuando sale a devolver
        $this->makeFacturaProveedor('20-02-' . static::$year, 5000.0);
        $servicio = new RegularizacionIGIC($helper);
        $this->assertNull($servicio->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1'));
        $this->assertSame(0, $this->contarRegularizaciones());
    }

    public function testFalloAMitadDeshaceTodo(): void
    {
        $this->makeTrimestre();
        $asientosAntes = Asiento::count();

        $servicio = new class () extends RegularizacionIGIC {
            protected function crearDeclaracion(RegularizacionImpuesto $regiva, int $idempresa): bool
            {
                return false;
            }
        };

        $this->assertNull($servicio->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1'));
        $this->assertSame(0, $this->contarRegularizaciones(), 'La regularización debe deshacerse');
        $this->assertSame($asientosAntes, Asiento::count(), 'El asiento debe deshacerse');
    }

    public function testFalloDentroDeUnaTransaccionExternaNoLaCierra(): void
    {
        $this->makeTrimestre();
        $servicio = new class () extends RegularizacionIGIC {
            protected function crearDeclaracion(RegularizacionImpuesto $regiva, int $idempresa): bool
            {
                return false;
            }
        };

        $db = new DataBase();
        $db->beginTransaction();
        $this->assertNull($servicio->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1'));
        $this->assertTrue($db->inTransaction(), 'La transacción externa debe seguir abierta');
        $db->rollback();
    }

    public function testEliminarBorraAsientoRegularizacionYDeclaracion(): void
    {
        $this->makeTrimestre();
        $servicio = new RegularizacionIGIC();
        $regiva = $servicio->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');
        $this->assertNotNull($regiva);
        $idasiento = $regiva->idasiento;

        $this->assertTrue($servicio->eliminar($regiva));
        $this->assertSame(0, $this->contarRegularizaciones());
        $this->assertFalse((new Asiento())->load($idasiento));
        $this->assertNull(RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva));
    }

    public function testEliminarDeclaracionPresentadaNoSePermite(): void
    {
        $this->makeTrimestre();
        $servicio = new RegularizacionIGIC();
        $regiva = $servicio->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');
        $declaracion = RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva);
        $this->assertTrue($declaracion->marcarPresentado('REF-1', '2090-04-15'));

        $this->assertFalse($servicio->eliminar($regiva));
        $this->assertSame(1, $this->contarRegularizaciones());
    }

    public function testEliminarConFalloDeshaceElBorrado(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2090-01-01', '2090-03-31', 'T1');

        $servicio = new class () extends RegularizacionIGIC {
            protected function eliminarTodo(RegularizacionImpuesto $regiva, ?DeclaracionIGIC $declaracion): bool
            {
                $declaracion->delete();
                return false;
            }
        };

        $this->assertFalse($servicio->eliminar($regiva));
        $this->assertNotNull(RegularizacionIGIC::getDeclaracion((int) $regiva->idregiva), 'El borrado debe deshacerse');
    }

    public function testGetPartidasSinAsiento(): void
    {
        $this->assertSame([], RegularizacionIGIC::getPartidas(new RegularizacionImpuesto()));
    }

    private function contarRegularizaciones(): int
    {
        return RegularizacionImpuesto::count([
            Where::gte('fechainicio', static::$year . '-01-01'),
            Where::lte('fechafin', static::$year . '-12-31'),
        ]);
    }
}
