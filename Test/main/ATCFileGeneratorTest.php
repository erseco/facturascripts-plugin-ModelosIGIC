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

use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Fichero del Modelo 420 para el programa de ayuda de la ATC.
 *
 * Las reglas proceden del programa de ayuda oficial (pa-mod420.jar); ver doc/NORMATIVA.md,
 * «Formato del fichero».
 */
final class ATCFileGeneratorTest extends TestCase
{
    /**
     * Importes con dos decimales implícitos: ConversorNumerico.numberToImpType().
     */
    public function testImporteSinPuntoDecimal(): void
    {
        $this->assertSame('7000', ATCFileGenerator::importe(70.0));
        $this->assertSame('000', ATCFileGenerator::importe(0.0));
        $this->assertSame('000', ATCFileGenerator::importe(-0.001));
        $this->assertSame('005', ATCFileGenerator::importe(0.05));
        $this->assertSame('-1250', ATCFileGenerator::importe(-12.5));
        $this->assertSame('950', ATCFileGenerator::importe(9.5));
        $this->assertSame('123457', ATCFileGenerator::importe(1234.567));
    }

    /**
     * Período del esquema (DEC/@PER): 1T–4T.
     */
    public function testPeriodo(): void
    {
        $this->assertSame('1T', ATCFileGenerator::periodo('T1'));
        $this->assertSame('4T', ATCFileGenerator::periodo('T4'));
        $this->assertSame('ANUAL', ATCFileGenerator::periodo('ANUAL'));
    }

    /**
     * Caracteres admitidos por el programa (DatosPersonales/Direccion, enum Campos).
     */
    public function testTextoNormalizado(): void
    {
        $this->assertSame('PEPITA GOMEZ, S.L.', ATCFileGenerator::texto(' Pepita  Gómez, S.L. ', 75));
        $this->assertSame('ÑANDU', ATCFileGenerator::texto('ñandú', 75));
        $this->assertSame('CALLE 1 2', ATCFileGenerator::texto('Calle 1º/2ª', 75));
        $this->assertSame('ABC', ATCFileGenerator::texto('abcdef', 3));
        $this->assertTrue(ATCFileGenerator::esPersonaFisica('12345678Z'));
        $this->assertTrue(ATCFileGenerator::esPersonaFisica('X1234567L'));
        $this->assertFalse(ATCFileGenerator::esPersonaFisica('B00000000'));
    }

    /**
     * UUEncoder/UUDecoder del programa: líneas de 45 bytes y el cero escrito como «`».
     */
    public function testUuencode(): void
    {
        $this->assertSame('', ATCFileGenerator::uuencode(''));
        $this->assertSame("#86)C\n", ATCFileGenerator::uuencode('abc'));
        $this->assertSame("!````\n", ATCFileGenerator::uuencode("\0"));

        $datos = random_bytes(200);
        $codificado = ATCFileGenerator::uuencode($datos);
        $lineas = explode("\n", rtrim($codificado, "\n"));
        $this->assertCount(5, $lineas);
        $this->assertSame('M', $lineas[0][0]);
        $this->assertSame(chr(20 + 32), $lineas[4][0]);
        $this->assertSame($datos, ATCFileGenerator::uudecode($codificado));
        $this->assertSame($datos, ATCFileGenerator::uudecode(str_replace("\n", "\r\n", $codificado) . "`\n"));
    }

    public function testCodificarYDecodificar(): void
    {
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n<DEC MOD=\"420\"/>\n";
        $this->assertSame($xml, ATCFileGenerator::decodificar(ATCFileGenerator::codificar($xml)));

        $this->expectException(RuntimeException::class);
        ATCFileGenerator::decodificar(ATCFileGenerator::uuencode('no es zlib'));
    }

    public function testFicheroAIngresar(): void
    {
        [$periodo, $casillas, $datos] = EjemplosFicheroATC::casos()['ingresar'];
        $generator = $this->generator($periodo, $casillas, $datos);
        $this->assertSame([], $generator->validar());
        $this->assertSame('I', $generator->tipoResultado());
        $this->assertEqualsWithDelta(480.0, $generator->resultado(), 0.001);
        $this->assertMatchesRegularExpression('/^B00000000-\d+\.atc$/', $generator->getFilename());

        $dec = simplexml_load_string(ATCFileGenerator::decodificar($generator->generate()));
        $this->assertSame('DEC', $dec->getName());
        $this->assertSame(['420', '2026', '1T', '9.3.0'], [
            (string) $dec['MOD'], (string) $dec['ANY'], (string) $dec['PER'], (string) $dec['VER'],
        ]);
        $otp = $dec->IDE->OTP;
        $this->assertSame('EMPRESA DE EJEMPLO CANARIAS, S.L.', (string) $otp['NRS']);
        $this->assertSame(['SP', 'CL', '35', '35016', '35001', 'ES'], [
            (string) $otp['TPE'], (string) $otp['SVP'], (string) $otp['POP'], (string) $otp['CMU'],
            (string) $otp['CP'], (string) $otp['PAI'],
        ]);
        $this->assertCount(2, $dec->IGI_DEV->DEV);
        $this->assertSame(['200000', '300', '6000'], [
            (string) $dec->IGI_DEV->DEV[0]['BAS'],
            (string) $dec->IGI_DEV->DEV[0]['TIP'],
            (string) $dec->IGI_DEV->DEV[0]['CUO'],
        ]);
        $this->assertSame('76000', (string) $dec->IGI_DEV['TOT']);
        $this->assertSame('400000', (string) $dec->IGI_DED->OIC['BAS']);
        $this->assertSame('28000', (string) $dec->IGI_DED['TOT']);
        $this->assertSame('48000', (string) $dec->LIQ['DIF']);
        $this->assertSame('48000', (string) $dec->LIQ['RLI']);
        $this->assertSame(['I', '48000', '5'], [
            (string) $dec->RES['TIP'], (string) $dec->RES['IMP'], (string) $dec->RES['FPA'],
        ]);
        $this->assertFalse(isset($dec->RES['IBAN']));
        $this->assertFalse(isset($dec['COM']));
        $this->assertFalse(isset($dec->ADI));
    }

    public function testFicheroACompensarConCasillasManuales(): void
    {
        [$periodo, $casillas, $datos] = EjemplosFicheroATC::casos()['compensar'];
        $generator = $this->generator($periodo, $casillas, $datos + [
            'c42' => '10', 'c43' => '5.5', 'c44' => '0', 'c46' => '100', 'c47' => '',
            'complementaria' => true, 'nja' => '4200000000001',
        ]);
        $this->assertSame([], $generator->validar());
        // 41 + 42 - 43 - 44 = -140 + 10 - 5,50
        $this->assertEqualsWithDelta(-135.5, $generator->resultado(), 0.001);
        $this->assertSame('C', $generator->tipoResultado());

        $dec = simplexml_load_string($generator->generarXML());
        $this->assertSame(['X', '4200000000001'], [(string) $dec['COM'], (string) $dec['NJA']]);
        $this->assertSame(['-14000', '1000', '550', '-13550'], [
            (string) $dec->LIQ['DIF'], (string) $dec->LIQ['RCU'], (string) $dec->LIQ['CPA'], (string) $dec->LIQ['RLI'],
        ]);
        $this->assertFalse(isset($dec->LIQ['DAC']));
        $this->assertSame(['C', '13550'], [(string) $dec->RES['TIP'], (string) $dec->RES['IMP']]);
        $this->assertSame('10000', (string) $dec->ADI['EOA']);
        $this->assertFalse(isset($dec->ADI['ODD']));
    }

    public function testFicheroADevolverYSinActividad(): void
    {
        [$periodo, $casillas, $datos] = EjemplosFicheroATC::casos()['devolver'];
        $generator = $this->generator($periodo, $casillas, $datos);
        $this->assertSame([], $generator->validar());
        $dec = simplexml_load_string($generator->generarXML());
        $this->assertSame(['D', '14000', 'ES9121000418450200051332'], [
            (string) $dec->RES['TIP'], (string) $dec->RES['IMP'], (string) $dec->RES['IBAN'],
        ]);
        $this->assertSame('000', (string) $dec->IGI_DEV->DEV[0]['TIP']);

        [$periodo, $casillas, $datos] = EjemplosFicheroATC::casos()['sin-actividad'];
        $generator = $this->generator($periodo, $casillas, $datos);
        $this->assertSame('S', $generator->tipoResultado());
        $dec = simplexml_load_string($generator->generarXML());
        $this->assertSame('S', (string) $dec->RES['TIP']);
        $this->assertFalse(isset($dec->RES['IMP']));
        $this->assertFalse(isset($dec->LIQ));
        $this->assertFalse(isset($dec->IGI_DEV));

        // con una casilla manual deja de ser «sin actividad»
        $generator->setDatos($datos + ['c43' => '20']);
        $this->assertSame('C', $generator->tipoResultado());
    }

    public function testValidacionDatosDelDeclarante(): void
    {
        [$periodo, $casillas] = EjemplosFicheroATC::casos()['ingresar'];
        $errores = $this->generator($periodo, $casillas, [
            'nif' => 'B0', 'svp' => 'ZZ', 'pop' => '38', 'cmu' => '35016', 'cp' => '350', 'npk' => '12A',
            'esc' => 'ABC', 'pis' => '123', 'pue' => '12345', 'tel' => '12-34', 'complementaria' => true,
            'nja' => '12',
        ])->validar();

        foreach (['nif', 'nrs', 'svp', 'nvp', 'cp', 'npk', 'esc', 'pis', 'pue', 'tel', 'nja', 'fpa'] as $campo) {
            $this->assertContains('fichero-atc-campo-' . $campo, $errores, $campo);
        }
        $this->assertContains('fichero-atc-municipio-provincia', $errores);
    }

    public function testValidacionResultadoYTipos(): void
    {
        [, $casillas, $datos] = EjemplosFicheroATC::casos()['devolver'];

        // la devolución solo en el 4T; el IBAN es obligatorio
        $errores = $this->generator('T2', $casillas, ['iban' => 'ES12'] + $datos)->validar();
        $this->assertContains('fichero-atc-devolucion-solo-4t', $errores);
        $this->assertContains('fichero-atc-campo-iban', $errores);

        // domiciliación sin IBAN
        [, $casillasIngreso] = EjemplosFicheroATC::casos()['ingresar'];
        $errores = $this->generator('T1', $casillasIngreso, ['fpa' => '4'] + EjemplosFicheroATC::SUJETO)->validar();
        $this->assertSame(['fichero-atc-campo-iban'], $errores);

        // tipo que no figura en la lista del programa (TiposGravamen.txt)
        $filas = [['tipo' => 6.5, 'base' => 100.0, 'cuota' => 6.5]];
        $errores = $this->ingreso($filas)->validar();
        $this->assertSame(['fichero-atc-tipo-no-admitido'], $errores);

        // el programa de 2025 no tiene el tipo del 1 % ni las filas 16b y 16c
        $filas = [['tipo' => 1.0, 'base' => 100.0, 'cuota' => 1.0]];
        $this->assertSame(['fichero-atc-tipo-no-admitido'], $this->ingreso($filas, '2025-01-01')->validar());

        $filas = [];
        foreach ([0, 3, 5, 7, 9.5, 15, 20] as $tipo) {
            $filas[] = ['tipo' => (float) $tipo, 'base' => 100.0, 'cuota' => $tipo];
        }
        $this->assertSame(['fichero-atc-demasiados-tipos'], $this->ingreso($filas, '2025-01-01')->validar());
        $this->assertSame([], $this->ingreso($filas)->validar());
    }

    public function testSoloModelo420DeEjerciciosConPrograma(): void
    {
        [$periodo, $casillas, $datos] = EjemplosFicheroATC::casos()['ingresar'];
        $generator = $this->generator($periodo, $casillas, $datos, '2024-01-01');
        $this->assertSame(['fichero-atc-ejercicio-no-soportado'], $generator->validar());

        $generator = $this->generator($periodo, $casillas, $datos);
        $generator->setDatos([]);
        $this->expectException(RuntimeException::class);
        $generator->generate();
    }

    public function testModelo425NoTieneFichero(): void
    {
        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = '425';
        $declaracion->periodo = 'ANUAL';
        $declaracion->fechainicio = '2026-01-01';
        $this->assertSame(['fichero-atc-solo-420'], (new ATCFileGenerator($declaracion))->validar());
    }

    /**
     * Declaración del 1T a ingresar, pagada en efectivo, con las filas de devengado indicadas.
     */
    private function ingreso(array $filas, string $inicio = '2026-01-01'): ATCFileGenerator
    {
        $casillas = EjemplosFicheroATC::casillas($filas, 0, 0, 'I');
        return $this->generator('T1', $casillas, ['fpa' => '1'] + EjemplosFicheroATC::SUJETO, $inicio);
    }

    private function generator(
        string $periodo,
        array $casillas,
        array $datos,
        string $inicio = '2026-01-01'
    ): ATCFileGenerator {
        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = '420';
        $declaracion->periodo = $periodo;
        $declaracion->fechainicio = $inicio;

        return (new ATCFileGenerator($declaracion))->setCasillas($casillas)->setDatos($datos);
    }
}
