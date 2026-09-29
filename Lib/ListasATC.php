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

/**
 * Listas de códigos que el programa de ayuda del 420 exige en los datos identificativos.
 *
 * Copiadas de los ficheros de la carpeta org_grecasa_ext_pa_items del programa de ayuda oficial
 * (pa-mod420.jar, versión 9.3.0 de 2026): Siglas.txt, Municipios-35.txt y Municipios-38.txt. El
 * validador del programa (ValidadorComunImpl.isDireccionValida()) comprueba la sigla contra la
 * lista «Siglas». Los códigos de municipio son los del INE (provincia + municipio).
 */
class ListasATC
{
    /** Siglas de la vía pública (Siglas.txt). */
    public const SIGLAS = [
        'AC' => 'ACCESO',
        'AG' => 'AGREGADO',
        'AL' => 'ALAMEDA',
        'AD' => 'ALDEA',
        'AP' => 'APARTAMENTO',
        'AR' => 'AREA, ARRABAL',
        'AY' => 'ARROYO',
        'AV' => 'AVENIDA',
        'AU' => 'AUTOPISTA',
        'AT' => 'AUTOVIA',
        'BD' => 'BARRIADA',
        'BJ' => 'BAJADA',
        'BR' => 'BARRANCO',
        'BO' => 'BARRIO',
        'BL' => 'BLOQUE',
        'BV' => 'BULEVAR',
        'CL' => 'CALLE',
        'CA' => 'CALLEJON',
        'CJ' => 'CALLEJA',
        'CC' => 'C. COMERCIAL',
        'CZ' => 'CALZADA',
        'CM' => 'CAMINO',
        'CP' => 'CAMPA',
        'CR' => 'CARRETERA',
        'CS' => 'CASERIO',
        'CH' => 'CHALET',
        'CG' => 'COLEGIO',
        'CO' => 'COLONIA',
        'CE' => 'COMPLEJO',
        'CN' => 'CONJUNTO',
        'CD' => 'CORREGIDOR',
        'CT' => 'CUESTA',
        'DP' => 'DIPUTACION',
        'DS' => 'DISEMINADOR',
        'ED' => 'EDIFICIO',
        'EN' => 'ENTRADA',
        'EC' => 'ESCALERA',
        'ES' => 'ESCALINATA',
        'EX' => 'EXPLANADA',
        'EM' => 'EXTRAMUROS',
        'ER' => 'EXTRARRADIO',
        'FC' => 'FERROCARRIL',
        'FN' => 'FINCA',
        'GL' => 'GLORIETA',
        'GV' => 'GRAN VIA',
        'GR' => 'GRUPO',
        'HT' => 'HUERTA',
        'JR' => 'JARDINES',
        'LR' => 'LADERA',
        'LD' => 'LADO',
        'LM' => 'LOMA',
        'LG' => 'LUGAR',
        'MZ' => 'MANZANA',
        'MS' => 'MASIA',
        'MC' => 'MERCADO',
        'MT' => 'MONTE',
        'ML' => 'MUELLE',
        'MN' => 'MUNICIPIO',
        'PO' => 'PAGO',
        'PE' => 'PARAJE',
        'PA' => 'PARCELA',
        'PQ' => 'PARROQUIA',
        'PD' => 'PARTIDA',
        'PJ' => 'PASAJE',
        'PS' => 'PASEO',
        'PI' => 'PISTA',
        'PC' => 'PLACETA',
        'PY' => 'PLAYA',
        'PZ' => 'PLAZA',
        'PL' => 'PLAZOLETA',
        'PB' => 'POBLADO',
        'PG' => 'POLIGONO',
        'PR' => 'PROLONGACION',
        'PT' => 'PUENTE',
        'PU' => 'PUERTA',
        'QT' => 'QUINTA',
        'RB' => 'RAMBLA',
        'RM' => 'RAMAL',
        'RP' => 'RAMPA',
        'RR' => 'RIERA',
        'RC' => 'RINCON',
        'RD' => 'RONDA',
        'RT' => 'ROTONDA',
        'RU' => 'RUA',
        'SA' => 'SALIDA',
        'SC' => 'SECTOR, SECCION',
        'SD' => 'SENDA',
        'SL' => 'SOLAR, SALON',
        'SB' => 'SUBIDA',
        'TN' => 'TERRENOS',
        'TO' => 'TORRENTE',
        'TL' => 'TRANSVERSAL',
        'TI' => 'TRASEIRA',
        'TS' => 'TRASERA',
        'TV' => 'TRAVESERA',
        'TR' => 'TRAVESIA',
        'UR' => 'URBANIZACION',
        'VL' => 'VALLE',
        'VD' => 'VEREDA',
        'VI' => 'VIA',
        'VP' => 'VIA PUBLICA',
        'ZO' => 'ZONA',
    ];

    /** Municipios de la provincia de Las Palmas (Municipios-35.txt). */
    public const MUNICIPIOS_35 = [
        '35001' => 'AGAETE',
        '35002' => 'AGUIMES',
        '35003' => 'ANTIGUA',
        '35004' => 'ARRECIFE',
        '35005' => 'ARTENARA',
        '35006' => 'ARUCAS',
        '35007' => 'BETANCURIA',
        '35008' => 'FIRGAS',
        '35009' => 'GALDAR',
        '35010' => 'HARIA',
        '35011' => 'INGENIO',
        '35012' => 'MOGAN',
        '35013' => 'MOYA',
        '35014' => 'LA OLIVA',
        '35015' => 'PAJARA',
        '35016' => 'LAS PALMAS DE GRAN CANARIA',
        '35017' => 'PUERTO DEL ROSARIO',
        '35018' => 'SAN BARTOLOME DE LANZAROTE',
        '35019' => 'SAN BARTOLOME DE TIRAJANA',
        '35020' => 'LA ALDEA DE SAN NICOLAS',
        '35021' => 'SANTA BRIGIDA',
        '35022' => 'SANTA LUCIA',
        '35023' => 'SANTA MARIA DE GUIA',
        '35024' => 'TEGUISE',
        '35025' => 'TEJEDA',
        '35026' => 'TELDE',
        '35027' => 'TEROR',
        '35028' => 'TIAS',
        '35029' => 'TINAJO',
        '35030' => 'TUINEJE',
        '35031' => 'VALSEQUILLO DE GRAN CANARIA',
        '35032' => 'VALLESECO',
        '35033' => 'VEGA DE SAN MATEO',
        '35034' => 'YAIZA',
    ];

    /** Municipios de la provincia de Santa Cruz de Tenerife (Municipios-38.txt). */
    public const MUNICIPIOS_38 = [
        '38001' => 'ADEJE',
        '38002' => 'AGULO',
        '38003' => 'ALAJERO',
        '38004' => 'ARAFO',
        '38005' => 'ARICO',
        '38006' => 'ARONA',
        '38007' => 'BARLOVENTO',
        '38008' => 'BREÑA ALTA',
        '38009' => 'BREÑA BAJA',
        '38010' => 'BUENAVISTA DEL NORTE',
        '38011' => 'CANDELARIA',
        '38012' => 'FASNIA',
        '38013' => 'FRONTERA',
        '38014' => 'FUENCALIENTE DE LA PALMA',
        '38015' => 'GARACHICO',
        '38016' => 'GARAFIA',
        '38017' => 'GRANADILLA DE ABONA',
        '38018' => 'GUANCHA (LA)',
        '38019' => 'GUIA DE ISORA',
        '38020' => 'GUIMAR',
        '38021' => 'HERMIGUA',
        '38022' => 'ICOD DE LOS VINOS',
        '38023' => 'LA LAGUNA',
        '38024' => 'LOS LLANOS DE ARIDANE',
        '38025' => 'MATANZA DE ACENTEJO (LA)',
        '38026' => 'LA OROTAVA',
        '38027' => 'PASO (EL)',
        '38028' => 'PUERTO DE LA CRUZ',
        '38029' => 'PUNTAGORDA',
        '38030' => 'PUNTALLANA',
        '38031' => 'LOS REALEJOS',
        '38032' => 'ROSARIO (EL)',
        '38033' => 'SAN ANDRES Y SAUCES',
        '38034' => 'SAN JUAN DE LA RAMBLA',
        '38035' => 'SAN MIGUEL',
        '38036' => 'SAN SEBASTIAN DE LA GOMERA',
        '38037' => 'SANTA CRUZ DE LA PALMA',
        '38038' => 'SANTA CRUZ DE TENERIFE',
        '38039' => 'SANTA URSULA',
        '38040' => 'SANTIAGO DEL TEIDE',
        '38041' => 'EL SAUZAL',
        '38042' => 'SILOS (LOS)',
        '38043' => 'TACORONTE',
        '38044' => 'TANQUE',
        '38045' => 'TAZACORTE',
        '38046' => 'TEGUESTE',
        '38047' => 'TIJARAFE',
        '38048' => 'VALVERDE DE HIERRO',
        '38049' => 'VALLE GRAN REY',
        '38050' => 'VALLEHERMOSO',
        '38051' => 'VICTORIA DE ACENTEJO (LA)',
        '38052' => 'VILAFLOR',
        '38053' => 'VILLA DE MAZO',
        '38901' => 'PINAR DE EL HIERRO (EL)',
    ];

    /**
     * Municipios de las dos provincias canarias, por código.
     */
    public static function municipiosCanarias(): array
    {
        return self::MUNICIPIOS_35 + self::MUNICIPIOS_38;
    }
}
