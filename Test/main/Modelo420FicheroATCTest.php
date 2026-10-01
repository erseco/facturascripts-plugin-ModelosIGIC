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
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ModelosIGIC\Controller\Modelo420;
use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
use FacturaScripts\Plugins\ModelosIGIC\Lib\RegularizacionIGIC;
use PHPUnit\Framework\TestCase;

/**
 * Descarga del fichero para el programa de ayuda desde la pantalla del Modelo 420.
 *
 * Usa el ejercicio 2026 porque el fichero solo se genera para ejercicios con un programa de
 * ayuda verificado (ATCFileGenerator::PROGRAMAS).
 */
final class Modelo420FicheroATCTest extends TestCase
{
    use ModelosIGICFixtures;

    protected function setUp(): void
    {
        static::$year = '2026';
        $this->login();
        Tools::settingsSet('modelosigic', 'fichero-atc-' . Empresas::default()->idempresa, '');
    }

    protected function tearDown(): void
    {
        $this->cleanFixtures();
        Tools::settingsSet('modelosigic', 'fichero-atc-' . Empresas::default()->idempresa, '');
        Tools::settingsSave();
        static::$year = '2090';
    }

    public function testDescargarFichero(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2026-01-01', '2026-03-31', 'T1');
        $this->assertNotNull($regiva, $this->recentLog());
        $id = ['id' => $regiva->idregiva];

        // el formulario se ofrece con los datos de la empresa
        $this->request('GET', [], $id);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);
        $this->assertTrue($controller->ficheroATCDisponible());
        $this->assertStringContainsString('modalFicheroATC', $html);
        $this->assertStringContainsString(Tools::lang()->trans('fichero-atc-experimental'), $html);
        $nif = ATCFileGenerator::texto((string) Empresas::default()->cifnif, 9);
        $this->assertSame($nif, $controller->datosFichero()['nif']);
        $this->assertArrayHasKey('CL', $controller->siglasVia());
        $this->assertSame('LAS PALMAS DE GRAN CANARIA', $controller->municipiosCanarias()['35016']);

        // sin los datos obligatorios no se descarga y se vuelve a abrir el formulario
        $datos = ['proceso' => 'descargar-atc', 'multireqtoken' => $this->formToken(), 'nif' => 'B00000000'];
        $this->request('POST', $datos, $id);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $html = $this->runController($controller);
        $this->assertStringContainsString(Tools::lang()->trans('fichero-atc-campo-svp'), $this->recentLog());
        $this->assertSame('B00000000', $controller->datosFichero()['nif']);
        $this->assertStringContainsString("getElementById('modalFicheroATC')", $html);

        // con los datos se descarga el fichero
        $this->request('POST', EjemplosFicheroATC::SUJETO + [
            'proceso' => 'descargar-atc', 'multireqtoken' => $this->formToken(), 'fpa' => '1',
            'iban' => 'ES9121000418450200051332',
        ], $id);
        $contenido = $this->runController(new Modelo420('Modelo420', '/Modelo420'));
        $this->assertStringContainsString('.atc"', $this->ultimaRespuesta->headers->get('Content-Disposition'));
        $dec = simplexml_load_string(ATCFileGenerator::decodificar($contenido));
        $this->assertSame(['420', '2026', '1T'], [(string) $dec['MOD'], (string) $dec['ANY'], (string) $dec['PER']]);
        $this->assertSame(['100000', '700', '7000'], [
            (string) $dec->IGI_DEV->DEV[0]['BAS'],
            (string) $dec->IGI_DEV->DEV[0]['TIP'],
            (string) $dec->IGI_DEV->DEV[0]['CUO'],
        ]);
        $this->assertSame('2800', (string) $dec->IGI_DED['TOT']);
        $this->assertSame(['I', '4200', '1'], [
            (string) $dec->RES['TIP'], (string) $dec->RES['IMP'], (string) $dec->RES['FPA'],
        ]);

        // los datos identificativos se recuerdan para el siguiente trimestre
        $this->request('GET', [], $id);
        $controller = new Modelo420('Modelo420', '/Modelo420');
        $this->runController($controller);
        $this->assertSame('35016', $controller->datosFichero()['cmu']);
        $this->assertSame('1', $controller->datosFichero()['fpa']);
        $this->assertArrayNotHasKey('c42', $controller->datosFichero());

        // el IBAN no se guarda en la configuración, que no va cifrada
        $this->assertArrayNotHasKey('iban', $controller->datosFichero());
        $guardado = (string) Tools::settings('modelosigic', 'fichero-atc-' . Empresas::default()->idempresa, '');
        $this->assertStringNotContainsString('ES9121000418450200051332', $guardado);
    }

    public function testSinTokenNoSeDescarga(): void
    {
        $this->makeTrimestre();
        $regiva = (new RegularizacionIGIC())->guardar($this->ejercicio(), '2026-01-01', '2026-03-31', 'T1');

        $datos = EjemplosFicheroATC::SUJETO + ['proceso' => 'descargar-atc', 'fpa' => '1'];
        $this->request('POST', $datos, ['id' => $regiva->idregiva]);
        $html = $this->runController(new Modelo420('Modelo420', '/Modelo420'));
        $this->assertStringContainsString('modalFicheroATC', $html);
        $this->assertNull($this->ultimaRespuesta->headers->get('Content-Disposition') ?: null);
    }

    private function request(string $method, array $data, array $query): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_GET = $query;
        $_POST = $data;
        $_REQUEST = array_merge($query, $data);
    }
}
