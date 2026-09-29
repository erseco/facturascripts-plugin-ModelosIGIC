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

namespace FacturaScripts\Test\Plugins\ModelosIGIC;

use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class IGICHelperTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(IGICHelper::class);
    }

    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(
            class_exists(IGICHelper::class),
            'IGICHelper class should exist'
        );
    }

    public function testCorrectNamespace(): void
    {
        $this->assertEquals(
            'FacturaScripts\\Plugins\\ModelosIGIC\\Lib',
            $this->reflection->getNamespaceName(),
            'IGICHelper should be in correct namespace'
        );
    }

    // =========================================================================
    // METHOD EXISTENCE TESTS
    // =========================================================================

    public function testDesgloseIGICComprasMethodExists(): void
    {
        $this->assertTrue(
            method_exists(IGICHelper::class, 'desgloseIGICCompras'),
            'desgloseIGICCompras method should exist'
        );
    }

    public function testDesgloseIGICVentasMethodExists(): void
    {
        $this->assertTrue(
            method_exists(IGICHelper::class, 'desgloseIGICVentas'),
            'desgloseIGICVentas method should exist'
        );
    }

    public function testHayFacturasSinAsientoMethodExists(): void
    {
        $this->assertTrue(
            method_exists(IGICHelper::class, 'hayFacturasSinAsiento'),
            'hayFacturasSinAsiento method should exist'
        );
    }

    public function testCalcularPeriodoActualMethodExists(): void
    {
        $this->assertTrue(
            method_exists(IGICHelper::class, 'calcularPeriodoActual'),
            'calcularPeriodoActual method should exist'
        );
    }

    public function testFechasPorPeriodoMethodExists(): void
    {
        $this->assertTrue(
            method_exists(IGICHelper::class, 'fechasPorPeriodo'),
            'fechasPorPeriodo method should exist'
        );
    }

    public function testCalcularRegularizacionMethodExists(): void
    {
        $this->assertTrue(
            method_exists(IGICHelper::class, 'calcularRegularizacion'),
            'calcularRegularizacion method should exist'
        );
    }

    public function testCalcularTotalDevengadoMethodExists(): void
    {
        $this->assertTrue(
            method_exists(IGICHelper::class, 'calcularTotalDevengado'),
            'calcularTotalDevengado method should exist'
        );
    }

    public function testCalcularTotalDeducibleMethodExists(): void
    {
        $this->assertTrue(
            method_exists(IGICHelper::class, 'calcularTotalDeducible'),
            'calcularTotalDeducible method should exist'
        );
    }

    public function testNombreTipoIGICMethodExists(): void
    {
        $this->assertTrue(
            method_exists(IGICHelper::class, 'nombreTipoIGIC'),
            'nombreTipoIGIC method should exist'
        );
    }

    // =========================================================================
    // METHOD VISIBILITY TESTS
    // =========================================================================

    public function testDesgloseIGICComprasIsPublic(): void
    {
        $method = $this->reflection->getMethod('desgloseIGICCompras');
        $this->assertTrue($method->isPublic(), 'desgloseIGICCompras should be public');
    }

    public function testDesgloseIGICVentasIsPublic(): void
    {
        $method = $this->reflection->getMethod('desgloseIGICVentas');
        $this->assertTrue($method->isPublic(), 'desgloseIGICVentas should be public');
    }

    // =========================================================================
    // RETURN TYPE TESTS
    // =========================================================================

    public function testDesgloseIGICComprasReturnsArray(): void
    {
        $method = $this->reflection->getMethod('desgloseIGICCompras');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'desgloseIGICCompras should have return type');
        $this->assertEquals('array', $returnType->getName());
    }

    public function testDesgloseIGICVentasReturnsArray(): void
    {
        $method = $this->reflection->getMethod('desgloseIGICVentas');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'desgloseIGICVentas should have return type');
        $this->assertEquals('array', $returnType->getName());
    }

    public function testHayFacturasSinAsientoReturnsBool(): void
    {
        $method = $this->reflection->getMethod('hayFacturasSinAsiento');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'hayFacturasSinAsiento should have return type');
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testCalcularPeriodoActualReturnsArray(): void
    {
        $method = $this->reflection->getMethod('calcularPeriodoActual');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'calcularPeriodoActual should have return type');
        $this->assertEquals('array', $returnType->getName());
    }

    public function testFechasPorPeriodoReturnsArray(): void
    {
        $method = $this->reflection->getMethod('fechasPorPeriodo');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'fechasPorPeriodo should have return type');
        $this->assertEquals('array', $returnType->getName());
    }

    public function testCalcularTotalDevengadoReturnsFloat(): void
    {
        $method = $this->reflection->getMethod('calcularTotalDevengado');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'calcularTotalDevengado should have return type');
        $this->assertEquals('float', $returnType->getName());
    }

    public function testCalcularTotalDeducibleReturnsFloat(): void
    {
        $method = $this->reflection->getMethod('calcularTotalDeducible');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'calcularTotalDeducible should have return type');
        $this->assertEquals('float', $returnType->getName());
    }

    public function testNombreTipoIGICReturnsString(): void
    {
        $method = $this->reflection->getMethod('nombreTipoIGIC');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'nombreTipoIGIC should have return type');
        $this->assertEquals('string', $returnType->getName());
    }
}
