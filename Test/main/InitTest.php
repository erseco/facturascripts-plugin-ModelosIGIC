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

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Plugins\ModelosIGIC\Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class InitTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(Init::class);
    }

    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(
            class_exists(Init::class),
            'Init class should exist'
        );
    }

    public function testExtendsInitClass(): void
    {
        $this->assertTrue(
            is_subclass_of(Init::class, InitClass::class),
            'Init should extend InitClass'
        );
    }

    public function testCorrectNamespace(): void
    {
        $this->assertEquals(
            'FacturaScripts\\Plugins\\ModelosIGIC',
            $this->reflection->getNamespaceName(),
            'Init should be in correct namespace'
        );
    }

    public function testClassIsFinal(): void
    {
        $this->assertTrue(
            $this->reflection->isFinal(),
            'Init class should be final'
        );
    }

    // =========================================================================
    // METHOD EXISTENCE TESTS
    // =========================================================================

    public function testInitMethodExists(): void
    {
        $this->assertTrue(
            method_exists(Init::class, 'init'),
            'init method should exist'
        );
    }

    public function testUpdateMethodExists(): void
    {
        $this->assertTrue(
            method_exists(Init::class, 'update'),
            'update method should exist'
        );
    }

    public function testUninstallMethodExists(): void
    {
        $this->assertTrue(
            method_exists(Init::class, 'uninstall'),
            'uninstall method should exist'
        );
    }

    // =========================================================================
    // METHOD VISIBILITY TESTS
    // =========================================================================

    public function testInitMethodIsPublic(): void
    {
        $method = $this->reflection->getMethod('init');
        $this->assertTrue($method->isPublic(), 'init method should be public');
    }

    public function testUpdateMethodIsPublic(): void
    {
        $method = $this->reflection->getMethod('update');
        $this->assertTrue($method->isPublic(), 'update method should be public');
    }

    public function testUninstallMethodIsPublic(): void
    {
        $method = $this->reflection->getMethod('uninstall');
        $this->assertTrue($method->isPublic(), 'uninstall method should be public');
    }

    // =========================================================================
    // METHOD RETURN TYPE TESTS
    // =========================================================================

    public function testInitMethodReturnType(): void
    {
        $method = $this->reflection->getMethod('init');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'init method should have a return type');
        $this->assertEquals('void', $returnType->getName());
    }

    public function testUpdateMethodReturnType(): void
    {
        $method = $this->reflection->getMethod('update');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'update method should have a return type');
        $this->assertEquals('void', $returnType->getName());
    }

    public function testUninstallMethodReturnType(): void
    {
        $method = $this->reflection->getMethod('uninstall');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'uninstall method should have a return type');
        $this->assertEquals('void', $returnType->getName());
    }

    // =========================================================================
    // CONTROLLER CLASSES EXISTENCE TESTS
    // =========================================================================

    public function testModelo420ControllerExists(): void
    {
        $this->assertTrue(
            class_exists('FacturaScripts\\Plugins\\ModelosIGIC\\Controller\\Modelo420'),
            'Modelo420 controller should exist'
        );
    }

    public function testModelo425ControllerExists(): void
    {
        $this->assertTrue(
            class_exists('FacturaScripts\\Plugins\\ModelosIGIC\\Controller\\Modelo425'),
            'Modelo425 controller should exist'
        );
    }

    // =========================================================================
    // HELPER CLASS EXISTENCE TESTS
    // =========================================================================

    public function testIGICHelperExists(): void
    {
        $this->assertTrue(
            class_exists('FacturaScripts\\Plugins\\ModelosIGIC\\Lib\\IGICHelper'),
            'IGICHelper class should exist'
        );
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testInitClassCanBeInstantiated(): void
    {
        // Init should be instantiable (without constructor args)
        $init = new Init();
        $this->assertInstanceOf(Init::class, $init);
    }

    public function testInitInheritsLoadExtensionMethod(): void
    {
        $this->assertTrue(
            method_exists(Init::class, 'loadExtension'),
            'Init should inherit loadExtension method from InitClass'
        );
    }
}
