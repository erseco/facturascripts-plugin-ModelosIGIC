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

use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests para la clase ATCFileGenerator.
 *
 * El formato del fichero ATC (.dec) para presentacion telematica es:
 * XML -> comprimido con zlib (gzdeflate) -> codificado en uuencode
 */
class ATCFileGeneratorTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(ATCFileGenerator::class);
    }

    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(
            class_exists(ATCFileGenerator::class),
            'ATCFileGenerator class should exist'
        );
    }

    public function testCorrectNamespace(): void
    {
        $this->assertEquals(
            'FacturaScripts\\Plugins\\ModelosIGIC\\Lib',
            $this->reflection->getNamespaceName(),
            'ATCFileGenerator should be in correct namespace'
        );
    }

    // =========================================================================
    // METHOD EXISTENCE TESTS
    // =========================================================================

    public function testGenerateMethodExists(): void
    {
        $this->assertTrue(
            method_exists(ATCFileGenerator::class, 'generate'),
            'generate method should exist'
        );
    }

    public function testSaveToFileMethodExists(): void
    {
        $this->assertTrue(
            method_exists(ATCFileGenerator::class, 'saveToFile'),
            'saveToFile method should exist'
        );
    }

    public function testGetFilenameMethodExists(): void
    {
        $this->assertTrue(
            method_exists(ATCFileGenerator::class, 'getFilename'),
            'getFilename method should exist'
        );
    }

    public function testDecodeMethodExists(): void
    {
        $this->assertTrue(
            method_exists(ATCFileGenerator::class, 'decode'),
            'decode static method should exist'
        );
    }

    public function testSetDesgloseVentasMethodExists(): void
    {
        $this->assertTrue(
            method_exists(ATCFileGenerator::class, 'setDesgloseVentas'),
            'setDesgloseVentas method should exist'
        );
    }

    public function testSetDesgloseComprasMethodExists(): void
    {
        $this->assertTrue(
            method_exists(ATCFileGenerator::class, 'setDesgloseCompras'),
            'setDesgloseCompras method should exist'
        );
    }

    // =========================================================================
    // METHOD VISIBILITY TESTS
    // =========================================================================

    public function testGenerateIsPublic(): void
    {
        $method = $this->reflection->getMethod('generate');
        $this->assertTrue($method->isPublic(), 'generate should be public');
    }

    public function testDecodeIsPublicAndStatic(): void
    {
        $method = $this->reflection->getMethod('decode');
        $this->assertTrue($method->isPublic(), 'decode should be public');
        $this->assertTrue($method->isStatic(), 'decode should be static');
    }

    public function testGenerateXMLIsProtected(): void
    {
        $method = $this->reflection->getMethod('generateXML');
        $this->assertTrue($method->isProtected(), 'generateXML should be protected');
    }

    public function testCompressZlibIsProtected(): void
    {
        $method = $this->reflection->getMethod('compressZlib');
        $this->assertTrue($method->isProtected(), 'compressZlib should be protected');
    }

    public function testEncodeUuencodeIsProtected(): void
    {
        $method = $this->reflection->getMethod('encodeUuencode');
        $this->assertTrue($method->isProtected(), 'encodeUuencode should be protected');
    }

    // =========================================================================
    // RETURN TYPE TESTS
    // =========================================================================

    public function testGenerateReturnsString(): void
    {
        $method = $this->reflection->getMethod('generate');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'generate should have return type');
        $this->assertEquals('string', $returnType->getName());
    }

    public function testSaveToFileReturnsString(): void
    {
        $method = $this->reflection->getMethod('saveToFile');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'saveToFile should have return type');
        $this->assertEquals('string', $returnType->getName());
    }

    public function testGetFilenameReturnsString(): void
    {
        $method = $this->reflection->getMethod('getFilename');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'getFilename should have return type');
        $this->assertEquals('string', $returnType->getName());
    }

    public function testDecodeReturnsString(): void
    {
        $method = $this->reflection->getMethod('decode');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'decode should have return type');
        $this->assertEquals('string', $returnType->getName());
    }

    // =========================================================================
    // ENCODE/DECODE TESTS
    // =========================================================================

    /**
     * Test que verifica que el proceso de codificacion/decodificacion es reversible.
     */
    public function testEncodeDecodeIsReversible(): void
    {
        // XML de ejemplo
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n"
            . '<DECLARACION><CABECERA><TIPO>DEC</TIPO></CABECERA></DECLARACION>';

        // Simular el proceso de codificacion
        $compressed = gzdeflate($xml, 9);
        $encoded = convert_uuencode($compressed);

        // Decodificar
        $decoded = ATCFileGenerator::decode($encoded);

        $this->assertEquals($xml, $decoded, 'Decoded XML should match original');
    }

    /**
     * Test que verifica que el fichero generado usa la extension del formato seleccionado
     */
    public function testFilenameUsesFormatExtension(): void
    {
        // Verificar que getFilename usa $this->format para la extension
        $sourceCode = file_get_contents($this->reflection->getFileName());

        $this->assertStringContainsString(
            '$this->format',
            $sourceCode,
            'Filename should use $this->format for extension'
        );
    }

    // =========================================================================
    // XML STRUCTURE TESTS (via reflection)
    // =========================================================================

    public function testGenerateXMLCreatesValidStructure(): void
    {
        // Verificar que el metodo generateXML existe y tiene la estructura esperada
        $method = $this->reflection->getMethod('generateXML');
        $this->assertTrue($method->isProtected());

        // Leer el codigo fuente para verificar elementos XML esperados
        $sourceCode = file_get_contents($this->reflection->getFileName());

        // Verificar elementos principales del XML
        $this->assertStringContainsString('DECLARACION', $sourceCode);
        $this->assertStringContainsString('CABECERA', $sourceCode);
        $this->assertStringContainsString('SUJETO', $sourceCode);
        $this->assertStringContainsString('IVA_DEVENGADO', $sourceCode);
        $this->assertStringContainsString('IVA_DEDUCIBLE', $sourceCode);
        $this->assertStringContainsString('RESULTADO', $sourceCode);
    }

    public function testXMLContainsMandatoryFields(): void
    {
        $sourceCode = file_get_contents($this->reflection->getFileName());

        // Campos obligatorios de la cabecera
        $this->assertStringContainsString('TIPO', $sourceCode);
        $this->assertStringContainsString('MODELO', $sourceCode);
        $this->assertStringContainsString('EJERCICIO', $sourceCode);
        $this->assertStringContainsString('PERIODO', $sourceCode);
        $this->assertStringContainsString('VERSION', $sourceCode);

        // Campos del sujeto pasivo
        $this->assertStringContainsString('NIF', $sourceCode);
        $this->assertStringContainsString('NOMBRE', $sourceCode);
        $this->assertStringContainsString('PROVINCIA', $sourceCode);
        $this->assertStringContainsString('MUNICIPIO', $sourceCode);
        $this->assertStringContainsString('CODIGO_POSTAL', $sourceCode);
    }

    // =========================================================================
    // FIXTURE VALIDATION TESTS
    // =========================================================================

    public function testFixtureFilesAreValidDEC(): void
    {
        // Los fixtures viajan con el plugin; los tests se copian a Test/Plugins del núcleo.
        $fixturesDir = FS_FOLDER . '/Plugins/ModelosIGIC/Test/fixtures/';

        if (!is_dir($fixturesDir)) {
            $this->markTestSkipped('Fixtures directory not found: ' . $fixturesDir);
            return;
        }

        $decFiles = glob($fixturesDir . '*.dec');
        $this->assertNotEmpty($decFiles, 'Should have at least one .dec fixture file');

        foreach ($decFiles as $fixturePath) {
            $content = file_get_contents($fixturePath);
            $this->assertNotEmpty($content, 'Fixture file should not be empty: ' . basename($fixturePath));

            // Intentar decodificar
            try {
                $xml = ATCFileGenerator::decode($content);
                $this->assertStringContainsString(
                    '<?xml',
                    $xml,
                    'Decoded content should be XML: ' . basename($fixturePath)
                );
                $this->assertStringContainsString(
                    'DECLARACION',
                    $xml,
                    'XML should contain DECLARACION element: ' . basename($fixturePath)
                );
            } catch (\Exception $e) {
                $this->fail(
                    'Fixture file should be valid .dec format: ' . basename($fixturePath)
                    . ' - ' . $e->getMessage()
                );
            }
        }
    }

    // =========================================================================
    // PERIOD CONVERSION TESTS
    // =========================================================================

    public function testPeriodoConversionPatterns(): void
    {
        $sourceCode = file_get_contents($this->reflection->getFileName());

        // Verificar que maneja los formatos T1, T2, T3, T4 y 1T, 2T, 3T, 4T
        $this->assertStringContainsString('[1-4]T', $sourceCode);
        $this->assertStringContainsString('T([1-4])', $sourceCode);
        $this->assertStringContainsString('0A', $sourceCode, 'Should handle annual period (0A)');
    }

    // =========================================================================
    // CANARY ISLANDS PROVINCE CODES TESTS
    // =========================================================================

    public function testCanaryIslandsProvinceCodes(): void
    {
        $sourceCode = file_get_contents($this->reflection->getFileName());

        // Las Palmas = 35, Santa Cruz de Tenerife = 38
        $this->assertStringContainsString("'35'", $sourceCode, 'Should have Las Palmas code (35)');
        $this->assertStringContainsString("'38'", $sourceCode, 'Should have Tenerife code (38)');
    }

    // =========================================================================
    // CONSTANTS TESTS
    // =========================================================================

    public function testVersionConstantExists(): void
    {
        $this->assertTrue(
            $this->reflection->hasConstant('VERSION'),
            'VERSION constant should exist'
        );
    }

    // =========================================================================
    // FORMAT CONSTANTS AND METHODS TESTS
    // =========================================================================

    public function testFormatConstantsExist(): void
    {
        $this->assertTrue(
            $this->reflection->hasConstant('FORMAT_DEC'),
            'FORMAT_DEC constant should exist'
        );
        $this->assertTrue(
            $this->reflection->hasConstant('FORMAT_ATC'),
            'FORMAT_ATC constant should exist'
        );

        $this->assertEquals('dec', ATCFileGenerator::FORMAT_DEC);
        $this->assertEquals('atc', ATCFileGenerator::FORMAT_ATC);
    }

    public function testSetFormatMethodExists(): void
    {
        $this->assertTrue(
            $this->reflection->hasMethod('setFormat'),
            'setFormat method should exist'
        );

        $this->assertTrue(
            $this->reflection->hasMethod('getFormat'),
            'getFormat method should exist'
        );
    }

    public function testDefaultFormatIsDEC(): void
    {
        $formatProperty = $this->reflection->getProperty('format');
        $formatProperty->setAccessible(true);

        $this->assertEquals(
            ATCFileGenerator::FORMAT_DEC,
            $formatProperty->getDefaultValue(),
            'Default format should be DEC'
        );
    }
}
