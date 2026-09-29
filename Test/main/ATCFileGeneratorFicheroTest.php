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
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

/**
 * Contenido del fichero generado para la ATC.
 *
 * Solo comprueba que el fichero refleja los datos de la declaración; el diseño
 * del fichero se revisará contra la normativa en la fase 3.
 */
final class ATCFileGeneratorFicheroTest extends TestCase
{
    use ModelosIGICFixtures;

    protected function tearDown(): void
    {
        $this->cleanFixtures();
    }

    public function testGeneraFicheroConLosDatosDeLaDeclaracion(): void
    {
        $generator = new ATCFileGenerator($this->declaracion('420', 'T1'));
        $generator->setDesgloseVentas([
            ['iva' => 7.0, 'recargo' => 0.0, 'neto' => 1000.0, 'totaliva' => 70.0, 'totalrecargo' => 0.0],
        ])->setDesgloseCompras([
            ['iva' => 7.0, 'recargo' => 0.0, 'neto' => 400.0, 'totaliva' => 28.0, 'totalrecargo' => 0.0],
        ]);

        $xml = simplexml_load_string(ATCFileGenerator::decode($generator->generate()));
        $this->assertSame('420', (string) $xml->CABECERA->MODELO);
        $this->assertSame('2090', (string) $xml->CABECERA->EJERCICIO);
        $this->assertSame('1T', (string) $xml->CABECERA->PERIODO);
        $this->assertSame((string) Empresas::default()->cifnif, (string) $xml->SUJETO->NIF);
        $this->assertSame('70.00', (string) $xml->IVA_DEVENGADO->TOTAL_CUOTA);
        $this->assertSame('28.00', (string) $xml->IVA_DEDUCIBLE->TOTAL);
        $this->assertSame('42.00', (string) $xml->RESULTADO->CUOTA_RESULTANTE);
        $this->assertSame('1', (string) $xml->RESULTADO->FORMA_PAGO);
    }

    public function testFormatoYNombreDeFichero(): void
    {
        $generator = new ATCFileGenerator($this->declaracion('425', 'ANUAL'));
        $this->assertSame(ATCFileGenerator::FORMAT_DEC, $generator->getFormat());
        $this->assertStringEndsWith('.dec', $generator->getFilename());

        $generator->setFormat(ATCFileGenerator::FORMAT_ATC);
        $this->assertSame(ATCFileGenerator::FORMAT_ATC, $generator->getFormat());
        $this->assertStringEndsWith('.atc', $generator->getFilename());

        $this->expectException(InvalidArgumentException::class);
        $generator->setFormat('pdf');
    }

    public function testGuardarEnDisco(): void
    {
        $generator = new ATCFileGenerator($this->declaracion('425', 'ANUAL'));
        $dir = sys_get_temp_dir() . '/modelosigic-' . uniqid();
        mkdir($dir);

        $path = $generator->saveToFile($dir);
        $this->assertFileExists($path);
        $xml = simplexml_load_string(ATCFileGenerator::decode(file_get_contents($path)));
        $this->assertSame('0A', (string) $xml->CABECERA->PERIODO);
        $this->assertSame('0', (string) $xml->RESULTADO->FORMA_PAGO);

        unlink($path);
        rmdir($dir);
        $this->assertFileExists($generator->saveToFile());
    }

    public function testPeriodos(): void
    {
        $esperado = ['2T' => '2T', 'T3' => '3T', 'ANUAL' => '0A', 'M01' => 'M01'];
        foreach ($esperado as $periodo => $atc) {
            $generator = new ATCFileGenerator($this->declaracion('420', $periodo, false));
            $xml = simplexml_load_string(ATCFileGenerator::decode($generator->generate()));
            $this->assertSame($atc, (string) $xml->CABECERA->PERIODO, $periodo);
        }
    }

    public function testDatosDelSujetoPersonaFisica(): void
    {
        $generator = new ATCFileGenerator($this->declaracion('420', 'T1', false));
        $empresa = new Empresa();
        $empresa->cifnif = '12345678Z';
        $empresa->nombre = 'Pepita Gómez Rodríguez';
        $empresa->provincia = 'Santa Cruz de Tenerife';
        $empresa->codpostal = '38001';
        $property = new ReflectionProperty(ATCFileGenerator::class, 'empresa');
        $property->setAccessible(true);
        $property->setValue($generator, $empresa);

        $xml = simplexml_load_string(ATCFileGenerator::decode($generator->generate()));
        $this->assertSame('Pepita', (string) $xml->SUJETO->NOMBRE);
        $this->assertSame('Gómez Rodríguez', (string) $xml->SUJETO->APELLIDOS);
        $this->assertSame('38', (string) $xml->SUJETO->PROVINCIA);
        $this->assertSame('38001', (string) $xml->SUJETO->MUNICIPIO);

        // persona jurídica sin código postal
        $empresa->cifnif = 'B35000000';
        $empresa->nombre = 'Ejemplo Canarias S.L.';
        $empresa->provincia = 'Las Palmas';
        $empresa->codpostal = '';
        $xml = simplexml_load_string(ATCFileGenerator::decode($generator->generate()));
        $this->assertSame('Ejemplo Canarias S.L.', (string) $xml->SUJETO->NOMBRE);
        $this->assertSame('', (string) $xml->SUJETO->APELLIDOS);
        $this->assertSame('35000', (string) $xml->SUJETO->MUNICIPIO);
    }

    public function testEmpresaPorDefectoSiNoHayEjercicio(): void
    {
        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = '420';
        $declaracion->periodo = 'T1';
        $declaracion->codejercicio = 'XXXX';

        $generator = new ATCFileGenerator($declaracion);
        $this->assertStringStartsWith((string) Empresas::default()->cifnif . '-', $generator->getFilename());
    }

    public function testDecodificarContenidoInvalido(): void
    {
        $this->expectException(RuntimeException::class);
        ATCFileGenerator::decode(convert_uuencode('no es zlib'));
    }

    private function declaracion(string $tipo, string $periodo, bool $save = true): DeclaracionIGIC
    {
        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = $tipo;
        $declaracion->periodo = $periodo;
        $declaracion->codejercicio = $this->ejercicio()->codejercicio;
        $declaracion->fechainicio = '2090-01-01';
        $declaracion->fechafin = '2090-03-31';
        if ($save) {
            $this->assertTrue($declaracion->save());
        }

        return $declaracion;
    }
}
