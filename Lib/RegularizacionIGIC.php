<?php

/**
 * This file is part of ModelosIGIC plugin for FacturaScripts.
 * Copyright (C) 2014-2026 Carlos Garcia Gomez <neorazorx@gmail.com>
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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGICFactura;

/**
 * Crea y elimina regularizaciones de IGIC (Modelo 420) con su asiento contable.
 *
 * Cada operación se ejecuta en una transacción: si falla cualquier paso no quedan
 * asientos, partidas, regularizaciones ni declaraciones a medias.
 */
class RegularizacionIGIC
{
    /** @var DataBase */
    protected DataBase $db;

    /** @var IGICHelper */
    protected IGICHelper $helper;

    public function __construct(?IGICHelper $helper = null)
    {
        $this->db = new DataBase();
        $this->helper = $helper ?? new IGICHelper();

        // las tablas no se pueden crear dentro de una transacción
        new Asiento();
        new Partida();
        new RegularizacionImpuesto();
        new DeclaracionIGIC();
        new DeclaracionIGICFactura();
    }

    /**
     * Devuelve la declaración del Modelo 420 asociada a una regularización.
     */
    public static function getDeclaracion(int $idregiva): ?DeclaracionIGIC
    {
        return DeclaracionIGIC::findWhere([Where::eq('idregiva', $idregiva)], ['idmodelo' => 'DESC']);
    }

    /**
     * Devuelve las partidas del asiento de una regularización.
     *
     * @return Partida[]
     */
    public static function getPartidas(RegularizacionImpuesto $regiva): array
    {
        if (empty($regiva->idasiento)) {
            return [];
        }

        return Partida::all([Where::eq('idasiento', $regiva->idasiento)], ['idpartida' => 'ASC']);
    }

    /**
     * Crea el asiento de regularización, la regularización y la declaración del Modelo 420.
     *
     * Devuelve la regularización guardada o null si no se ha podido crear.
     */
    public function guardar(
        Ejercicio $ejercicio,
        string $desde,
        string $hasta,
        string $periodo
    ): ?RegularizacionImpuesto {
        if ($this->helper->hayFacturasSinAsiento($desde, $hasta, (int) $ejercicio->idempresa)) {
            Tools::log()->error('facturas-sin-asiento');
            return null;
        }

        if ($this->haySolapamiento((int) $ejercicio->idempresa, $desde, $hasta)) {
            Tools::log()->error('regularizacion-solapada');
            return null;
        }

        $lineas = $this->helper->calcularRegularizacion($desde, $hasta, $ejercicio->codejercicio);
        if (empty($lineas)) {
            Tools::log()->warning('sin-datos-regularizacion');
            return null;
        }

        if (false === $this->comprobarCuadre($lineas)) {
            return null;
        }

        $newTransaction = false === $this->db->inTransaction() && $this->db->beginTransaction();
        $regiva = $this->crearRegularizacion($ejercicio, $desde, $hasta, $periodo, $lineas);
        if (null === $regiva) {
            if ($newTransaction) {
                $this->db->rollback();
            }
            return null;
        }

        if ($newTransaction) {
            $this->db->commit();
        }

        return $regiva;
    }

    /**
     * Elimina una regularización, su asiento y su declaración en borrador.
     */
    public function eliminar(RegularizacionImpuesto $regiva): bool
    {
        $declaracion = static::getDeclaracion((int) $regiva->idregiva);
        if ($declaracion !== null && $declaracion->estado !== 'borrador') {
            Tools::log()->warning('declaracion-no-eliminable');
            return false;
        }

        $newTransaction = false === $this->db->inTransaction() && $this->db->beginTransaction();
        if (false === $this->eliminarTodo($regiva, $declaracion)) {
            if ($newTransaction) {
                $this->db->rollback();
            }
            Tools::log()->error('error-eliminar-regularizacion');
            return false;
        }

        if ($newTransaction) {
            $this->db->commit();
        }

        return true;
    }

    /**
     * Comprueba que las partidas propuestas cuadran.
     *
     * Solo descuadran cuando falta la subcuenta especial de cierre (IVAACR o IVADEU).
     */
    protected function comprobarCuadre(array $lineas): bool
    {
        $debe = 0.0;
        $haber = 0.0;
        foreach ($lineas as $linea) {
            $debe += $linea['debe'];
            $haber += $linea['haber'];
        }

        $decimales = (int) Tools::settings('default', 'decimals', 2);
        if (Tools::floatCmp($debe, $haber, $decimales, true)) {
            return true;
        }

        Tools::log()->error($debe > $haber ? 'subcuenta-acreedora-no-encontrada' : 'subcuenta-deudora-no-encontrada');
        return false;
    }

    protected function crearAsiento(Ejercicio $ejercicio, string $hasta, string $periodo, array $lineas): ?Asiento
    {
        $asiento = new Asiento();
        $asiento->idempresa = $ejercicio->idempresa;
        $asiento->codejercicio = $ejercicio->codejercicio;
        $asiento->concepto = Tools::lang()->trans('concepto-regularizacion-igic', ['%periodo%' => $periodo]);
        $asiento->fecha = $hasta;
        if (false === $asiento->save()) {
            Tools::log()->error('error-guardar-asiento');
            return null;
        }

        foreach ($lineas as $linea) {
            $partida = $asiento->getNewLine($linea['subcuenta']);
            $partida->debe = round($linea['debe'], 2);
            $partida->haber = round($linea['haber'], 2);
            if (false === $partida->save()) {
                Tools::log()->error('error-guardar-partida');
                return null;
            }
            $asiento->importe += $partida->debe;
        }

        // bloqueamos el asiento una vez creadas sus partidas
        $asiento->editable = false;
        if (false === $asiento->save() || false === $asiento->isBalanced()) {
            Tools::log()->error('error-guardar-asiento');
            return null;
        }

        return $asiento;
    }

    protected function crearDeclaracion(RegularizacionImpuesto $regiva, int $idempresa): bool
    {
        $ventas = $this->helper->desgloseIGICVentas($regiva->fechainicio, $regiva->fechafin, $idempresa);
        $compras = $this->helper->desgloseIGICCompras($regiva->fechainicio, $regiva->fechafin, $idempresa);

        $declaracion = new DeclaracionIGIC();
        $declaracion->tipo = '420';
        $declaracion->periodo = $regiva->periodo;
        $declaracion->codejercicio = $regiva->codejercicio;
        $declaracion->fechainicio = $regiva->fechainicio;
        $declaracion->fechafin = $regiva->fechafin;
        $declaracion->idregiva = $regiva->idregiva;
        $declaracion->totaldevengado = $this->helper->calcularTotalDevengado($ventas);
        $declaracion->totaldeducible = $this->helper->calcularTotalDeducible($compras);
        $declaracion->resultado = $declaracion->totaldevengado - $declaracion->totaldeducible;
        $declaracion->estado = 'borrador';

        return $declaracion->save() && $declaracion->guardarFacturas($idempresa);
    }

    protected function crearRegularizacion(
        Ejercicio $ejercicio,
        string $desde,
        string $hasta,
        string $periodo,
        array $lineas
    ): ?RegularizacionImpuesto {
        $asiento = $this->crearAsiento($ejercicio, $hasta, $periodo, $lineas);
        if (null === $asiento) {
            return null;
        }

        $regiva = new RegularizacionImpuesto();
        $regiva->codejercicio = $ejercicio->codejercicio;
        $regiva->idempresa = $ejercicio->idempresa;
        $regiva->fechaasiento = $asiento->fecha;
        $regiva->fechainicio = $desde;
        $regiva->fechafin = $hasta;
        $regiva->idasiento = $asiento->idasiento;
        $regiva->periodo = $periodo;
        if (false === $regiva->save()) {
            Tools::log()->error('error-guardar-regularizacion');
            return null;
        }

        if (false === $this->crearDeclaracion($regiva, (int) $ejercicio->idempresa)) {
            Tools::log()->error('error-guardar-declaracion');
            return null;
        }

        return $regiva;
    }

    protected function eliminarTodo(RegularizacionImpuesto $regiva, ?DeclaracionIGIC $declaracion): bool
    {
        if ($declaracion !== null && false === $declaracion->delete()) {
            return false;
        }

        // desbloqueamos el asiento para que la regularización pueda eliminarlo
        $asiento = new Asiento();
        if ($regiva->idasiento && $asiento->load($regiva->idasiento)) {
            $asiento->editable = true;
            if (false === $asiento->save()) {
                return false;
            }
        }

        if (false === $regiva->delete()) {
            return false;
        }

        // RegularizacionImpuesto::delete() no comprueba el borrado del asiento
        return empty($regiva->idasiento) || false === $asiento->load($regiva->idasiento);
    }

    /**
     * Comprueba si ya hay una regularización de la empresa que se solape con el período.
     */
    protected function haySolapamiento(int $idempresa, string $desde, string $hasta): bool
    {
        return RegularizacionImpuesto::count([
            Where::eq('idempresa', $idempresa),
            Where::lte('fechainicio', $hasta),
            Where::gte('fechafin', $desde),
        ]) > 0;
    }
}
