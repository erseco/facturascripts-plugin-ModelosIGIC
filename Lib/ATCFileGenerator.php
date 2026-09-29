<?php

/**
 * This file is part of ModelosIGIC plugin for FacturaScripts.
 * Copyright (C) 2026 Ernesto Serrano <info@ernesto.es>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\ModelosIGIC\Lib;

use DOMDocument;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;

/**
 * Generador de ficheros para la Agencia Tributaria Canaria (ATC).
 *
 * Soporta dos formatos de fichero:
 * - .atc: Fichero de intercambio para importar/exportar entre programas de ayuda
 * - .dec: Fichero para presentación telemática en la Sede Electrónica
 *
 * Ambos formatos usan la misma codificación interna:
 * XML (ISO-8859-1) → comprimido con zlib (gzdeflate nivel 9) → codificado en uuencode
 */
class ATCFileGenerator
{
    public const FORMAT_DEC = 'dec';
    public const FORMAT_ATC = 'atc';

    private const VERSION = '9.2.0';

    /** @var Empresa */
    protected Empresa $empresa;

    /** @var DeclaracionIGIC */
    protected DeclaracionIGIC $modelo;

    /** @var array */
    protected array $desgloseVentas = [];

    /** @var array */
    protected array $desgloseCompras = [];

    /** @var string Formato de salida: 'dec' o 'atc' */
    protected string $format = self::FORMAT_DEC;

    public function __construct(DeclaracionIGIC $modelo)
    {
        $this->modelo = $modelo;
        $this->empresa = new Empresa();
        $this->empresa->loadFromCode($this->empresa->idempresa);
    }

    /**
     * Establece el desglose de ventas (IGIC devengado).
     */
    public function setDesgloseVentas(array $desglose): self
    {
        $this->desgloseVentas = $desglose;
        return $this;
    }

    /**
     * Establece el desglose de compras (IGIC deducible).
     */
    public function setDesgloseCompras(array $desglose): self
    {
        $this->desgloseCompras = $desglose;
        return $this;
    }

    /**
     * Establece el formato de salida.
     *
     * @param string $format Formato: FORMAT_DEC para presentación telemática,
     *                       FORMAT_ATC para importación/exportación
     */
    public function setFormat(string $format): self
    {
        if (!in_array($format, [self::FORMAT_DEC, self::FORMAT_ATC], true)) {
            throw new \InvalidArgumentException('Formato no válido. Use FORMAT_DEC o FORMAT_ATC');
        }
        $this->format = $format;
        return $this;
    }

    /**
     * Obtiene el formato de salida actual.
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Genera el fichero .dec y lo devuelve como string.
     */
    public function generate(): string
    {
        $xml = $this->generateXML();
        $compressed = $this->compressZlib($xml);
        return $this->encodeUuencode($compressed);
    }

    /**
     * Genera el fichero .dec y lo guarda en disco.
     *
     * @return string Ruta del fichero generado
     */
    public function saveToFile(string $directory = ''): string
    {
        if (empty($directory)) {
            $directory = sys_get_temp_dir();
        }

        $filename = $this->getFilename();
        $filepath = rtrim($directory, '/') . '/' . $filename;

        file_put_contents($filepath, $this->generate());

        return $filepath;
    }

    /**
     * Genera el nombre del fichero según el formato de la ATC.
     * Formato: NIF-timestamp.dec o NIF-timestamp.atc
     */
    public function getFilename(): string
    {
        $nif = $this->empresa->cifnif ?? 'UNKNOWN';
        $timestamp = time() * 1000 + rand(0, 999);
        return $nif . '-' . $timestamp . '.' . $this->format;
    }

    /**
     * Genera el XML del modelo 420.
     */
    protected function generateXML(): string
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dom->formatOutput = true;

        // Elemento raíz
        $declaracion = $dom->createElement('DECLARACION');
        $dom->appendChild($declaracion);

        // Cabecera
        $cabecera = $dom->createElement('CABECERA');
        $declaracion->appendChild($cabecera);
        $this->addElement($dom, $cabecera, 'TIPO', 'DEC');
        $this->addElement($dom, $cabecera, 'MODELO', $this->modelo->tipo);
        $this->addElement($dom, $cabecera, 'EJERCICIO', substr($this->modelo->codejercicio, 0, 4));
        $this->addElement($dom, $cabecera, 'PERIODO', $this->getPeriodoATC());
        $this->addElement($dom, $cabecera, 'VERSION', self::VERSION);

        // Sujeto pasivo
        $sujeto = $dom->createElement('SUJETO');
        $declaracion->appendChild($sujeto);
        $this->addElement($dom, $sujeto, 'NIF', $this->empresa->cifnif);
        $this->addElement($dom, $sujeto, 'NOMBRE', $this->getNombreEmpresa());
        $this->addElement($dom, $sujeto, 'APELLIDOS', $this->getApellidosEmpresa());
        $this->addElement($dom, $sujeto, 'PROVINCIA', $this->getCodigoProvincia());
        $this->addElement($dom, $sujeto, 'MUNICIPIO', $this->getCodigoMunicipio());
        $this->addElement($dom, $sujeto, 'CODIGO_POSTAL', $this->empresa->codpostal ?? '');
        $this->addElement($dom, $sujeto, 'PAIS', 'ES');

        // IGIC Devengado (ventas)
        $ivaDevengado = $dom->createElement('IVA_DEVENGADO');
        $declaracion->appendChild($ivaDevengado);
        $totalDevengado = 0.0;

        foreach ($this->desgloseVentas as $item) {
            $registro = $dom->createElement('REGISTRO');
            $ivaDevengado->appendChild($registro);
            $this->addElement($dom, $registro, 'BASE', $this->formatNumber($item['neto']));
            $this->addElement($dom, $registro, 'TIPO', $this->formatNumber($item['iva']));
            $cuota = $item['totaliva'] + ($item['totalrecargo'] ?? 0);
            $this->addElement($dom, $registro, 'CUOTA', $this->formatNumber($cuota));
            $totalDevengado += $cuota;
        }

        $this->addElement($dom, $ivaDevengado, 'TOTAL_CUOTA', $this->formatNumber($totalDevengado));

        // IGIC Deducible (compras)
        $ivaDeducible = $dom->createElement('IVA_DEDUCIBLE');
        $declaracion->appendChild($ivaDeducible);
        $totalDeducible = 0.0;

        foreach ($this->desgloseCompras as $item) {
            $cuota = $item['totaliva'] + ($item['totalrecargo'] ?? 0);
            $totalDeducible += $cuota;
        }

        $this->addElement($dom, $ivaDeducible, 'TOTAL', $this->formatNumber($totalDeducible));

        // Resultado
        $resultado = $dom->createElement('RESULTADO');
        $declaracion->appendChild($resultado);
        $cuotaResultante = $totalDevengado - $totalDeducible;
        $this->addElement($dom, $resultado, 'CUOTA_RESULTANTE', $this->formatNumber($cuotaResultante));
        // 1 = ingreso normal, 2 = domiciliación, etc.
        $this->addElement($dom, $resultado, 'FORMA_PAGO', $cuotaResultante > 0 ? '1' : '0');
        $this->addElement($dom, $resultado, 'IBAN', '');

        return $dom->saveXML();
    }

    /**
     * Comprime el XML con zlib.
     */
    protected function compressZlib(string $data): string
    {
        // Usar compresión raw deflate (sin header gzip/zlib)
        return gzdeflate($data, 9);
    }

    /**
     * Codifica los datos comprimidos en formato uuencode.
     */
    protected function encodeUuencode(string $data): string
    {
        return convert_uuencode($data);
    }

    /**
     * Obtiene el período en formato ATC (1T, 2T, 3T, 4T).
     */
    protected function getPeriodoATC(): string
    {
        $periodo = $this->modelo->periodo;

        // Si ya está en formato correcto
        if (preg_match('/^[1-4]T$/', $periodo)) {
            return $periodo;
        }

        // Convertir de T1, T2, T3, T4 a 1T, 2T, 3T, 4T
        if (preg_match('/^T([1-4])$/', $periodo, $matches)) {
            return $matches[1] . 'T';
        }

        // Para modelo 425 anual
        if ($periodo === 'ANUAL' || $this->modelo->tipo === '425') {
            return '0A';
        }

        return $periodo;
    }

    /**
     * Obtiene el nombre de la empresa (para personas jurídicas) o nombre propio.
     */
    protected function getNombreEmpresa(): string
    {
        $nombre = $this->empresa->nombre ?? '';

        // Si es persona física, intentar separar nombre y apellidos
        if ($this->esPersonaFisica()) {
            $partes = explode(' ', trim($nombre), 2);
            return $partes[0] ?? $nombre;
        }

        return $nombre;
    }

    /**
     * Obtiene los apellidos (solo para personas físicas).
     */
    protected function getApellidosEmpresa(): string
    {
        if (!$this->esPersonaFisica()) {
            return '';
        }

        $nombre = $this->empresa->nombre ?? '';
        $partes = explode(' ', trim($nombre), 2);
        return $partes[1] ?? '';
    }

    /**
     * Comprueba si el NIF corresponde a una persona física.
     */
    protected function esPersonaFisica(): bool
    {
        $nif = $this->empresa->cifnif ?? '';
        // NIF de persona física empieza por número o X, Y, Z
        return preg_match('/^[0-9XYZ]/i', $nif) === 1;
    }

    /**
     * Obtiene el código de provincia (2 dígitos).
     */
    protected function getCodigoProvincia(): string
    {
        $provincia = $this->empresa->provincia ?? '';

        // Mapa de provincias canarias
        $provincias = [
            'las palmas' => '35',
            'santa cruz de tenerife' => '38',
            'tenerife' => '38',
            'gran canaria' => '35',
        ];

        $provinciaLower = strtolower(trim($provincia));
        return $provincias[$provinciaLower] ?? '35';
    }

    /**
     * Obtiene el código de municipio (5 dígitos: provincia + municipio).
     */
    protected function getCodigoMunicipio(): string
    {
        $codpostal = $this->empresa->codpostal ?? '';
        $provincia = $this->getCodigoProvincia();

        // Intentar extraer el código de municipio del código postal
        if (strlen($codpostal) >= 5) {
            return substr($codpostal, 0, 5);
        }

        return $provincia . '000';
    }

    /**
     * Formatea un número con 2 decimales.
     */
    protected function formatNumber(float $number): string
    {
        return number_format($number, 2, '.', '');
    }

    /**
     * Añade un elemento al DOM.
     */
    protected function addElement(DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $element = $dom->createElement($name, htmlspecialchars($value, ENT_XML1, 'ISO-8859-1'));
        $parent->appendChild($element);
    }

    /**
     * Decodifica un fichero .dec existente y devuelve el XML.
     *
     * @param string $content Contenido del fichero .dec
     *
     * @return string XML decodificado
     */
    public static function decode(string $content): string
    {
        // Decodificar uuencode
        $decoded = convert_uudecode($content);
        if ($decoded === false) {
            throw new \RuntimeException('Error al decodificar uuencode');
        }

        // Descomprimir zlib
        $xml = gzinflate($decoded);
        if ($xml === false) {
            throw new \RuntimeException('Error al descomprimir zlib');
        }

        return $xml;
    }
}
