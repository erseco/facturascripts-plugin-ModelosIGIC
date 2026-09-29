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
use DOMElement;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use RuntimeException;

/**
 * Fichero del Modelo 420 para importar en el programa de ayuda de la ATC (EXPERIMENTAL).
 *
 * El formato se ha obtenido del propio programa de ayuda oficial, porque la ATC no publica un
 * diseño de registro (doc/NORMATIVA.md, «Formato del fichero»):
 * - Contenido: XML con el esquema `Presentacion-420-XMLSchema.xsd` y `Comunes_Presentacion.xsd`
 *   del programa (nodo raíz DEC), en ISO-8859-1.
 * - Codificación: `org.grecasa.ext.codificador.Codificador.codifica()`: el XML se comprime con
 *   `java.util.zip.DeflaterOutputStream` (zlib con cabecera) y se codifica con
 *   `org.grecasa.ext.codificador.UUEncoder` (líneas de 45 bytes, sin líneas begin/end).
 * - Nombre y extensión: `GestorDeclaracionesComunImpl` guarda las declaraciones como
 *   `NIF-milisegundos.atc` y `GestorDeclaracionesImpl.importarDeclaraciones()` solo importa
 *   ficheros `.atc` del ejercicio del programa.
 *
 * El fichero no se presenta directamente en la sede: se importa en el programa de ayuda, que
 * lo valida y genera la presentación (doc/VALIDAR_FICHERO_ATC.md).
 */
class ATCFileGenerator
{
    public const EXTENSION = 'atc';

    /** Formas de pago del resultado a ingresar admitidas (programa: FormasPago.txt, sin la 3). */
    public const FORMAS_PAGO = ['1', '2', '4', '5'];

    /** Formas de pago que exigen IBAN (programa: PAModuloUtils.isRequeridoCodigoIban()). */
    public const FORMAS_PAGO_CON_IBAN = ['2', '4'];

    /**
     * Programas de ayuda oficiales verificados, por ejercicio.
     *
     * - version: valor de DEC/@VER que fija ObjectUtils.crearDEC() de cada programa.
     * - filas: filas de IGIC devengado del programa (nombres_campos.properties: 01–18 en 2025;
     *   01–18, 16b–18b y 16c–18c en 2026).
     * - tipos: lista oficial de tipos de gravamen del programa (TiposGravamen.txt).
     */
    public const PROGRAMAS = [
        '2025' => ['version' => '9.2.0', 'filas' => 6, 'tipos' => [0, 3, 5, 7, 9.5, 15, 20]],
        '2026' => ['version' => '9.3.0', 'filas' => 8, 'tipos' => [0, 1, 3, 5, 7, 9.5, 15, 20]],
    ];

    /** @var array Casillas calculadas por CasillasModelo420::calcular() */
    protected array $casillas = [];

    /** @var array Datos que aporta el declarante */
    protected array $datos = [];

    /** @var DeclaracionIGIC */
    protected DeclaracionIGIC $declaracion;

    /** @var string Milisegundos para el nombre del fichero */
    protected string $marcaTiempo;

    public function __construct(DeclaracionIGIC $declaracion)
    {
        $this->declaracion = $declaracion;
        $this->marcaTiempo = (string) (int) round(microtime(true) * 1000);
    }

    /**
     * Casillas del modelo calculadas por CasillasModelo420::calcular().
     */
    public function setCasillas(array $casillas): self
    {
        $this->casillas = $casillas;
        return $this;
    }

    /**
     * Datos del declarante y de la liquidación que FacturaScripts no registra.
     *
     * Claves: nif, nrs, svp, nvp, npk, esc, pis, pue, pop, cmu, cp, tel, c42, c43, c44, c46,
     * c47, complementaria, nja, tipo (C o D si el resultado es negativo), fpa e iban.
     */
    public function setDatos(array $datos): self
    {
        $this->datos = $datos;
        return $this;
    }

    public function getEjercicio(): string
    {
        return date('Y', strtotime((string) $this->declaracion->fechainicio));
    }

    /**
     * Nombre del fichero con el formato del programa de ayuda: NIF-milisegundos.atc.
     */
    public function getFilename(): string
    {
        $nif = self::texto((string) ($this->datos['nif'] ?? ''), 9);
        return ($nif ?: 'NIF') . '-' . $this->marcaTiempo . '.' . self::EXTENSION;
    }

    /**
     * Resultado de la autoliquidación: 41 + 42 - 43 - 44 (Instrucciones 420, casilla 45;
     * programa: CalculosModelo420.calcularResultado()).
     */
    public function resultado(): float
    {
        return round(
            $this->importeCasilla('41') + $this->dato('c42') - $this->dato('c43') - $this->dato('c44'),
            2
        );
    }

    /**
     * Tipo de resultado (DEC/RES/@TIP): I, D, C o S.
     *
     * Programa: ValidadorComunImpl.isTipoResultadoValido(). Si el resultado es negativo el
     * declarante elige compensar (C) o, en el 4T, devolver (D).
     */
    public function tipoResultado(): string
    {
        if ($this->sinActividad()) {
            return 'S';
        }

        $resultado = $this->resultado();
        if ($resultado > 0) {
            return 'I';
        }

        return $resultado < 0 && ($this->datos['tipo'] ?? '') === 'D' ? 'D' : 'C';
    }

    /**
     * Comprueba los datos antes de generar el fichero.
     *
     * @return string[] Claves de traducción de los errores encontrados
     */
    public function validar(): array
    {
        $errores = [];
        if ($this->declaracion->tipo !== '420') {
            return ['fichero-atc-solo-420'];
        }

        $programa = self::PROGRAMAS[$this->getEjercicio()] ?? null;
        if (null === $programa) {
            return ['fichero-atc-ejercicio-no-soportado'];
        }

        $obligatorios = [
            'nif' => '/^[0-9A-Z]{9}$/',
            'nrs' => '/^[0-9A-ZÑ ,.\-]{1,75}$/u',
            'nvp' => '/^[0-9A-ZÑ ,.\-]{1,50}$/u',
            'pop' => '/^[0-9]{2}$/',
            'cmu' => '/^[0-9]{5}$/',
            'cp' => '/^[0-9]{5}$/',
        ];
        foreach ($obligatorios as $campo => $patron) {
            if (1 !== preg_match($patron, $this->valorTexto($campo))) {
                $errores[] = 'fichero-atc-campo-' . $campo;
            }
        }

        $opcionales = [
            'npk' => '/^[0-9]{1,5}$/',
            'esc' => '/^[0-9A-ZÑ ,.\-]{1,2}$/u',
            'pis' => '/^[0-9A-ZÑ ,.\-]{1,2}$/u',
            'pue' => '/^[0-9A-ZÑ ,.\-]{1,4}$/u',
            'tel' => '/^[0-9]{1,15}$/',
        ];
        foreach ($opcionales as $campo => $patron) {
            $valor = $this->valorTexto($campo);
            if ($valor !== '' && 1 !== preg_match($patron, $valor)) {
                $errores[] = 'fichero-atc-campo-' . $campo;
            }
        }

        if (false === isset(ListasATC::SIGLAS[$this->valorTexto('svp')])) {
            $errores[] = 'fichero-atc-campo-svp';
        }

        if (substr($this->valorTexto('cmu'), 0, 2) !== $this->valorTexto('pop')) {
            $errores[] = 'fichero-atc-municipio-provincia';
        }

        $nja = (string) ($this->datos['nja'] ?? '');
        if (!empty($this->datos['complementaria']) && 1 !== preg_match('/^[0-9]{13}$/', $nja)) {
            $errores[] = 'fichero-atc-campo-nja';
        }

        foreach ($this->casillas['filas'] ?? [] as $i => $fila) {
            if ($i >= $programa['filas']) {
                $errores[] = 'fichero-atc-demasiados-tipos';
                break;
            }
            if (false === in_array((float) $fila['tipo'], array_map('floatval', $programa['tipos']), true)) {
                $errores[] = 'fichero-atc-tipo-no-admitido';
                break;
            }
        }

        $tipo = $this->tipoResultado();
        if ($tipo === 'D' && $this->declaracion->periodo !== 'T4') {
            $errores[] = 'fichero-atc-devolucion-solo-4t';
        }
        if ($tipo === 'I' && false === in_array($this->datos['fpa'] ?? '', self::FORMAS_PAGO, true)) {
            $errores[] = 'fichero-atc-campo-fpa';
        }
        if ($this->requiereIban() && 1 !== preg_match('/^ES[0-9]{22}$/', $this->iban())) {
            $errores[] = 'fichero-atc-campo-iban';
        }

        return $errores;
    }

    /**
     * Genera el fichero codificado listo para importar en el programa de ayuda.
     */
    public function generate(): string
    {
        $errores = $this->validar();
        if (!empty($errores)) {
            throw new RuntimeException(implode(', ', $errores));
        }

        return self::codificar($this->generarXML());
    }

    /**
     * XML del Modelo 420 según el esquema del programa de ayuda (ISO-8859-1).
     */
    public function generarXML(): string
    {
        $programa = self::PROGRAMAS[$this->getEjercicio()] ?? ['version' => ''];

        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dec = $dom->createElement('DEC');
        $dom->appendChild($dec);
        $dec->setAttribute('MOD', '420');
        $dec->setAttribute('ANY', $this->getEjercicio());
        $dec->setAttribute('PER', self::periodo((string) $this->declaracion->periodo));
        if (!empty($this->datos['complementaria'])) {
            $dec->setAttribute('COM', 'X');
            $dec->setAttribute('NJA', (string) $this->datos['nja']);
        }
        $dec->setAttribute('VER', $programa['version']);

        // IDE/OTP: sujeto pasivo (ObjectUtilsComun.obtenerDatosIdentificativosDEC())
        $ide = $this->hijo($dom, $dec, 'IDE');
        $otp = $this->hijo($dom, $ide, 'OTP');
        $otp->setAttribute('SEC', '1');
        $otp->setAttribute('TPE', 'SP');
        foreach (['nif' => 'NIF', 'nrs' => 'NRS', 'svp' => 'SVP', 'nvp' => 'NVP'] as $campo => $atributo) {
            $otp->setAttribute($atributo, $this->valorTexto($campo));
        }
        $opcionales = ['npk' => 'NPK', 'esc' => 'ESC', 'pis' => 'PIS', 'pue' => 'PUE', 'tel' => 'TEL'];
        foreach ($opcionales as $campo => $atributo) {
            if ($this->valorTexto($campo) !== '') {
                $otp->setAttribute($atributo, $this->valorTexto($campo));
            }
        }
        $otp->setAttribute('POP', $this->valorTexto('pop'));
        $otp->setAttribute('CMU', $this->valorTexto('cmu'));
        $otp->setAttribute('CP', $this->valorTexto('cp'));
        $otp->setAttribute('PAI', 'ES');

        if (false === $this->sinActividad()) {
            $this->nodosLiquidacion($dom, $dec);
        }

        // RES: resultado (ObjectUtilsComun.obtenerRESULTADOLIQUIDACION())
        $tipo = $this->tipoResultado();
        $res = $this->hijo($dom, $dec, 'RES');
        $res->setAttribute('TIP', $tipo);
        if ($tipo !== 'S') {
            $res->setAttribute('IMP', self::importe(abs($this->resultado())));
        }
        if ($tipo === 'I') {
            $res->setAttribute('FPA', (string) $this->datos['fpa']);
        }
        if ($this->requiereIban()) {
            $res->setAttribute('IBAN', $this->iban());
        }

        if (false === $this->sinActividad()) {
            $this->nodoInformacionAdicional($dom, $dec);
        }

        return (string) $dom->saveXML();
    }

    /**
     * Comprime y codifica el XML como Codificador.codifica() del programa de ayuda.
     */
    public static function codificar(string $xml): string
    {
        return self::uuencode((string) gzcompress($xml));
    }

    /**
     * Decodifica un fichero del programa de ayuda (Codificador.decodifica()).
     */
    public static function decodificar(string $contenido): string
    {
        $xml = @gzuncompress(self::uudecode($contenido));
        if (false === $xml) {
            throw new RuntimeException('fichero-atc-no-valido');
        }

        return $xml;
    }

    /**
     * Importe con dos decimales implícitos (tipos IMPA15Type e IMPA5Type del esquema).
     *
     * Equivale a ConversorNumerico.numberToImpType() del programa: redondeo HALF_UP a dos
     * decimales y se quita el punto; 70,00 € se escribe «7000» y 0 € «000».
     */
    public static function importe(float $valor): string
    {
        $valor = round($valor, 2);
        if (abs($valor) < 0.005) {
            return '000';
        }

        return str_replace('.', '', sprintf('%.2f', $valor));
    }

    /**
     * NIF de persona física: empieza por un dígito o por K, L, M, X, Y o Z.
     *
     * Solo sirve para no rellenar el nombre: en personas físicas el programa pide apellidos y
     * nombre, que el declarante debe escribir; el plugin no los separa.
     */
    public static function esPersonaFisica(string $nif): bool
    {
        return 1 === preg_match('/^[0-9KLMXYZ]/i', trim($nif));
    }

    /**
     * Período del esquema: 1T, 2T, 3T o 4T (atributo DEC/@PER).
     */
    public static function periodo(string $periodo): string
    {
        return 1 === preg_match('/^T([1-4])$/', $periodo, $match) ? $match[1] . 'T' : $periodo;
    }

    /**
     * Texto con los caracteres que admite el programa de ayuda: mayúsculas sin tildes, Ñ,
     * dígitos, espacio, coma, punto y guion (DatosPersonales y Direccion, enum Campos).
     */
    public static function texto(string $valor, int $longitud): string
    {
        $valor = mb_strtoupper(trim($valor), 'UTF-8');
        $valor = strtr($valor, [
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I', 'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U', 'Ç' => 'C',
        ]);
        $valor = (string) preg_replace('/[^0-9A-ZÑ ,.\-]/u', ' ', $valor);
        $valor = trim((string) preg_replace('/\s+/', ' ', $valor));

        return mb_substr($valor, 0, $longitud, 'UTF-8');
    }

    /**
     * Codificación UU del programa (org.grecasa.ext.codificador.UUEncoder).
     *
     * Líneas de 45 bytes que empiezan por «M», la última con su longitud, el valor 0 escrito como
     * «`» y sin líneas «begin»/«end» ni línea final vacía.
     */
    public static function uuencode(string $datos): string
    {
        $salida = '';
        foreach (str_split($datos, 45) as $linea) {
            $n = strlen($linea);
            $salida .= chr(($n & 0x3F) + 32);
            $linea = str_pad($linea, (int) ceil($n / 3) * 3, "\0");
            for ($i = 0; $i < strlen($linea); $i += 3) {
                $b0 = ord($linea[$i]);
                $b1 = ord($linea[$i + 1]);
                $b2 = ord($linea[$i + 2]);
                $valores = [$b0 >> 2, (($b0 << 4) & 0x30) | ($b1 >> 4), (($b1 << 2) & 0x3C) | ($b2 >> 6), $b2 & 0x3F];
                foreach ($valores as $v) {
                    $salida .= $v === 0 ? '`' : chr($v + 32);
                }
            }
            $salida .= "\n";
        }

        return $datos === '' ? '' : $salida;
    }

    /**
     * Decodificación UU del programa (org.grecasa.ext.codificador.UUDecoder).
     */
    public static function uudecode(string $contenido): string
    {
        $salida = '';
        foreach (preg_split('/\r?\n/', $contenido) as $linea) {
            if ($linea === '') {
                continue;
            }

            $n = (ord($linea[0]) - 32) & 0x3F;
            if ($n <= 0) {
                break;
            }

            $linea = str_pad($linea, (intdiv($n + 2, 3) << 2) + 1, ' ');
            for ($bp = 1; $n > 0; $bp += 4, $n -= 3) {
                $c = array_map(static fn ($ch) => (ord($ch) - 32) & 0x3F, str_split(substr($linea, $bp, 4)));
                $bytes = [
                    ($c[0] << 2 | $c[1] >> 4) & 0xFF,
                    ($c[1] << 4 | $c[2] >> 2) & 0xFF,
                    ($c[2] << 6 | $c[3]) & 0xFF,
                ];
                foreach (array_slice($bytes, 0, min(3, $n)) as $byte) {
                    $salida .= chr($byte);
                }
            }
        }

        return $salida;
    }

    protected function dato(string $clave): float
    {
        $valor = $this->datos[$clave] ?? '';
        return $valor === '' || $valor === null ? 0.0 : round((float) $valor, 2);
    }

    protected function hijo(DOMDocument $dom, DOMElement $padre, string $nombre): DOMElement
    {
        $nodo = $dom->createElement($nombre);
        $padre->appendChild($nodo);
        return $nodo;
    }

    protected function iban(): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) ($this->datos['iban'] ?? '')));
    }

    protected function importeCasilla(string $casilla): float
    {
        return (float) ($this->casillas['casillas'][$casilla]['importe'] ?? 0.0);
    }

    /**
     * ADI: información adicional, casillas 46 y 47 (T_INFO_ADICIONAL).
     */
    protected function nodoInformacionAdicional(DOMDocument $dom, DOMElement $dec): void
    {
        if ($this->dato('c46') == 0 && $this->dato('c47') == 0) {
            return;
        }

        $adi = $this->hijo($dom, $dec, 'ADI');
        if ($this->dato('c46') != 0) {
            $adi->setAttribute('EOA', self::importe($this->dato('c46')));
        }
        if ($this->dato('c47') != 0) {
            $adi->setAttribute('ODD', self::importe($this->dato('c47')));
        }
    }

    /**
     * IGI_DEV, IGI_DED y LIQ (T_DEVENGADO, T_DEDUCIBLE y T_LIQUIDACION del esquema).
     */
    protected function nodosLiquidacion(DOMDocument $dom, DOMElement $dec): void
    {
        // IGI_DEV/DEV: una fila por tipo (casillas 01–18, 16b–18c); TOT = casilla 25
        $filas = $this->casillas['filas'] ?? [];
        if (!empty($filas)) {
            $dev = $this->hijo($dom, $dec, 'IGI_DEV');
            foreach ($filas as $fila) {
                $nodo = $this->hijo($dom, $dev, 'DEV');
                $nodo->setAttribute('BAS', self::importe((float) $fila['base']));
                $nodo->setAttribute('TIP', self::importe((float) $fila['tipo']));
                $nodo->setAttribute('CUO', self::importe((float) $fila['cuota']));
            }
            $dev->setAttribute('TOT', self::importe($this->importeCasilla('25')));
        }

        // IGI_DED/OIC: operaciones interiores con bienes corrientes, casillas 26 y 27; TOT = 40
        if ($this->importeCasilla('26') != 0 || $this->importeCasilla('27') != 0) {
            $ded = $this->hijo($dom, $dec, 'IGI_DED');
            $oic = $this->hijo($dom, $ded, 'OIC');
            $oic->setAttribute('BAS', self::importe($this->importeCasilla('26')));
            $oic->setAttribute('CUO', self::importe($this->importeCasilla('27')));
            $ded->setAttribute('TOT', self::importe($this->importeCasilla('40')));
        }

        // LIQ: 41 diferencia, 42 regularización art. 22.8.5ª, 43 a compensar, 44 a deducir, 45
        $liq = $this->hijo($dom, $dec, 'LIQ');
        $liq->setAttribute('DIF', self::importe($this->importeCasilla('41')));
        foreach (['c42' => 'RCU', 'c43' => 'CPA', 'c44' => 'DAC'] as $clave => $atributo) {
            if ($this->dato($clave) != 0) {
                $liq->setAttribute($atributo, self::importe($this->dato($clave)));
            }
        }
        $liq->setAttribute('RLI', self::importe($this->resultado()));
    }

    protected function requiereIban(): bool
    {
        $tipo = $this->tipoResultado();
        $fpa = $this->datos['fpa'] ?? '';
        return $tipo === 'D' || ($tipo === 'I' && in_array($fpa, self::FORMAS_PAGO_CON_IBAN, true));
    }

    /**
     * Sin actividad (S): no hay operaciones en el período y el declarante no ha consignado nada.
     */
    protected function sinActividad(): bool
    {
        return ($this->casillas['resultado'] ?? '') === 'S'
            && $this->dato('c42') == 0 && $this->dato('c43') == 0 && $this->dato('c44') == 0
            && $this->dato('c46') == 0 && $this->dato('c47') == 0;
    }

    /**
     * Valor de un campo normalizado; la longitud la comprueba validar(), no se recorta.
     */
    protected function valorTexto(string $campo): string
    {
        $valor = (string) ($this->datos[$campo] ?? '');
        if (in_array($campo, ['nrs', 'nvp', 'nif', 'svp', 'npk', 'esc', 'pis', 'pue'], true)) {
            return self::texto($valor, 200);
        }

        return (string) preg_replace('/\s+/', '', $valor);
    }
}
