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

use DOMDocument;
use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use PHPUnit\Framework\TestCase;

/**
 * El fichero se compara con el programa de ayuda oficial del Modelo 420.
 *
 * - Codificación: Test/fixtures/codificador-oficial.atc lo generó Codificador.codifica() del
 *   programa (pa-mod420.jar v9.3.0).
 * - Esquema: si MODELOSIGIC_XSD_DIR apunta a la carpeta org_grecasa_ext_pa/xsd del programa
 *   (el CI la descarga de la web de la ATC), los ejemplos se validan con el XSD oficial. Los XSD
 *   no se incluyen en el repositorio.
 * - Los ejemplos de doc/ejemplos/ se comprobaron además con el validador y el importador del
 *   programa (Test/atc/validar.sh).
 */
final class ATCFicheroOficialTest extends TestCase
{
    private const PLUGIN = FS_FOLDER . '/Plugins/ModelosIGIC/';

    public function testCodificacionIdenticaAlProgramaOficial(): void
    {
        $xml = file_get_contents(self::PLUGIN . 'Test/fixtures/codificador-oficial.xml');
        $oficial = file_get_contents(self::PLUGIN . 'Test/fixtures/codificador-oficial.atc');

        $this->assertSame($oficial, ATCFileGenerator::codificar($xml));
        $this->assertSame($xml, ATCFileGenerator::decodificar($oficial));
    }

    public function testEjemplosDocumentadosCoincidenConElGenerador(): void
    {
        foreach (EjemplosFicheroATC::casos() as $nombre => [$periodo, $casillas, $datos]) {
            $generator = $this->generator($periodo, $casillas, $datos);
            $base = self::PLUGIN . 'doc/ejemplos/modelo420-2026-' . $nombre;

            $this->assertSame(file_get_contents($base . '.xml'), $generator->generarXML(), $nombre);
            $this->assertSame(
                file_get_contents($base . '.xml'),
                ATCFileGenerator::decodificar(file_get_contents($base . '.atc')),
                $nombre
            );
        }
    }

    public function testEjemplosValidosSegunElEsquemaOficial(): void
    {
        $dir = (string) getenv('MODELOSIGIC_XSD_DIR');
        if ($dir === '' || false === is_file($dir . '/Presentacion-420-XMLSchema.xsd')) {
            $this->markTestSkipped('MODELOSIGIC_XSD_DIR no apunta a los XSD del programa de ayuda del 420');
        }

        $esquema = $this->esquema($dir);
        foreach (EjemplosFicheroATC::casos() as $nombre => [$periodo, $casillas, $datos]) {
            $dom = new DOMDocument();
            $dom->loadXML($this->generator($periodo, $casillas, $datos)->generarXML());

            libxml_use_internal_errors(true);
            $valido = $dom->schemaValidate($esquema);
            $errores = array_map(static fn ($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            $this->assertTrue($valido, $nombre . ': ' . implode('; ', $errores));
        }
    }

    /**
     * Copia el esquema del programa arreglando dos detalles que impiden cargarlo fuera de él:
     * un comentario antes de la declaración XML y el include de un acceso directo de Windows
     * (Comunes_Presentacion.xsd.lnk), que dentro del programa apunta a Comunes_Presentacion.xsd.
     */
    private function esquema(string $dir): string
    {
        $tmp = sys_get_temp_dir() . '/modelosigic-xsd-' . uniqid();
        mkdir($tmp);
        $xsd = file_get_contents($dir . '/Presentacion-420-XMLSchema.xsd');
        $xsd = substr($xsd, (int) strpos($xsd, '<?xml'));
        file_put_contents(
            $tmp . '/Presentacion-420-XMLSchema.xsd',
            str_replace('Comunes_Presentacion.xsd.lnk', 'Comunes_Presentacion.xsd', $xsd)
        );
        copy($dir . '/Comunes_Presentacion.xsd', $tmp . '/Comunes_Presentacion.xsd');

        return $tmp . '/Presentacion-420-XMLSchema.xsd';
    }

    private function generator(string $periodo, array $casillas, array $datos): ATCFileGenerator
    {
        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = '420';
        $declaracion->periodo = $periodo;
        $declaracion->fechainicio = '2026-01-01';

        return (new ATCFileGenerator($declaracion))->setCasillas($casillas)->setDatos($datos);
    }
}
