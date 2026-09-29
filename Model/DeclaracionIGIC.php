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

namespace FacturaScripts\Plugins\ModelosIGIC\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
use Throwable;

/**
 * Modelo para almacenar los modelos fiscales presentados (420 y 425).
 *
 * Esta tabla permite hacer seguimiento de los modelos presentados,
 * las facturas incluidas en cada uno, y crear modelos rectificativos.
 */
class DeclaracionIGIC extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $idmodelo;

    /** @var string Tipo de modelo: "420" o "425" */
    public $tipo;

    /** @var string Período: T1, T2, T3, T4, ANUAL */
    public $periodo;

    /** @var string Código del ejercicio fiscal */
    public $codejercicio;

    /** @var string Fecha de inicio del período */
    public $fechainicio;

    /** @var string Fecha de fin del período */
    public $fechafin;

    /** @var string|null Fecha de presentación */
    public $fechapresentacion;

    /** @var int|null ID de la regularización asociada */
    public $idregiva;

    /** @var int|null ID del modelo que rectifica (si aplica) */
    public $idrectifica;

    /** @var float Total IGIC devengado */
    public $totaldevengado;

    /** @var float Total IGIC deducible */
    public $totaldeducible;

    /** @var float Resultado (ingresar/devolver) */
    public $resultado;

    /** @var string Estado: borrador, presentado, rectificado */
    public $estado;

    /** @var string|null Número de referencia de la ATC */
    public $numeroreferencia;

    /** @var string Fecha de creación */
    public $fechacreacion;

    public function clear(): void
    {
        parent::clear();
        $this->estado = 'borrador';
        $this->fechapresentacion = null;
        $this->idregiva = null;
        $this->idrectifica = null;
        $this->numeroreferencia = null;
        $this->totaldevengado = 0.0;
        $this->totaldeducible = 0.0;
        $this->resultado = 0.0;
        $this->fechacreacion = date('Y-m-d H:i:s');
    }

    public function install(): string
    {
        // dependencias de las claves foráneas
        new Ejercicio();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'idmodelo';
    }

    public static function tableName(): string
    {
        return 'declaraciones_igic';
    }

    /**
     * Obtiene las facturas incluidas en este modelo.
     *
     * @return DeclaracionIGICFactura[]
     */
    public function getFacturas(): array
    {
        return DeclaracionIGICFactura::all([Where::eq('idmodelo', $this->idmodelo)], ['fecha' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Obtiene las facturas de cliente incluidas en este modelo.
     *
     * @return DeclaracionIGICFactura[]
     */
    public function getFacturasCliente(): array
    {
        return DeclaracionIGICFactura::all([
            Where::eq('idmodelo', $this->idmodelo),
            Where::eq('tipofactura', 'cliente'),
        ], ['fecha' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Obtiene las facturas de proveedor incluidas en este modelo.
     *
     * @return DeclaracionIGICFactura[]
     */
    public function getFacturasProveedor(): array
    {
        return DeclaracionIGICFactura::all([
            Where::eq('idmodelo', $this->idmodelo),
            Where::eq('tipofactura', 'proveedor'),
        ], ['fecha' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Devuelve la empresa del ejercicio de la declaración.
     */
    public function getIdEmpresa(): ?int
    {
        $ejercicio = new Ejercicio();
        return $ejercicio->load($this->codejercicio) ? (int) $ejercicio->idempresa : null;
    }

    /**
     * Comprueba si este modelo es rectificativo.
     */
    public function esRectificativo(): bool
    {
        return !empty($this->idrectifica);
    }

    /**
     * Obtiene el modelo que rectifica este (si aplica).
     */
    public function getModeloRectificado(): ?self
    {
        if (empty($this->idrectifica)) {
            return null;
        }

        $modelo = new self();
        return $modelo->load($this->idrectifica) ? $modelo : null;
    }

    /**
     * Obtiene los modelos rectificativos de este modelo.
     *
     * @return self[]
     */
    public function getModelosRectificativos(): array
    {
        return self::all([Where::eq('idrectifica', $this->idmodelo)], ['idmodelo' => 'ASC']);
    }

    /**
     * Devuelve la descripción del estado.
     */
    public function estadoDescripcion(): string
    {
        return match ($this->estado) {
            'borrador' => Tools::lang()->trans('estado-borrador'),
            'presentado' => Tools::lang()->trans('estado-presentado'),
            'rectificado' => Tools::lang()->trans('estado-rectificado'),
            default => $this->estado,
        };
    }

    /**
     * Devuelve la clase CSS para el estado.
     */
    public function estadoClase(): string
    {
        return match ($this->estado) {
            'borrador' => 'warning',
            'presentado' => 'success',
            'rectificado' => 'secondary',
            default => 'primary',
        };
    }

    /**
     * Marca el modelo como presentado.
     */
    public function marcarPresentado(?string $numeroReferencia = null, ?string $fechaPresentacion = null): bool
    {
        $this->estado = 'presentado';
        $this->numeroreferencia = $numeroReferencia;
        $this->fechapresentacion = $fechaPresentacion ?? date('Y-m-d');
        return $this->save();
    }

    /**
     * Crea un modelo rectificativo basado en este.
     *
     * Todo se hace en una transacción: si falla cualquier paso no queda
     * el original marcado como rectificado ni un rectificativo a medias.
     */
    public function crearRectificativo(): ?self
    {
        if ($this->estado !== 'presentado') {
            return null;
        }

        // las tablas no se pueden crear dentro de una transacción
        new DeclaracionIGICFactura();

        $db = self::db();
        $newTransaction = false === $db->inTransaction() && $db->beginTransaction();

        try {
            $nuevo = $this->copiarComoRectificativo();
        } catch (Throwable $exception) {
            if ($newTransaction) {
                $db->rollback();
            }
            throw $exception;
        }

        if (null === $nuevo) {
            if ($newTransaction) {
                $db->rollback();
            }
            $this->estado = 'presentado';
            return null;
        }

        if ($newTransaction) {
            $db->commit();
        }

        return $nuevo;
    }

    /**
     * Elimina la declaración junto con las facturas registradas en ella.
     */
    public function delete(): bool
    {
        new DeclaracionIGICFactura();

        $db = self::db();
        $newTransaction = false === $db->inTransaction() && $db->beginTransaction();

        try {
            $borrado = $this->borrarConFacturas();
        } catch (Throwable $exception) {
            if ($newTransaction) {
                $db->rollback();
            }
            throw $exception;
        }

        if (false === $borrado) {
            if ($newTransaction) {
                $db->rollback();
            }
            return false;
        }

        if ($newTransaction) {
            $db->commit();
        }

        return true;
    }

    /**
     * Registra en la declaración las facturas de cliente y proveedor de su período.
     *
     * Si se indica una empresa, solo se registran sus facturas.
     */
    public function guardarFacturas(?int $idempresa = null): bool
    {
        // mismo criterio de período que el cálculo del modelo (fecha de devengo o, si no hay, fecha)
        $where = IGICHelper::wherePeriodo($this->fechainicio, $this->fechafin, $idempresa);

        foreach (FacturaCliente::all($where, ['fecha' => 'ASC', 'idfactura' => 'ASC']) as $factura) {
            if (false === DeclaracionIGICFactura::fromFacturaCliente($factura, (int) $this->idmodelo)->save()) {
                return false;
            }
        }

        foreach (FacturaProveedor::all($where, ['fecha' => 'ASC', 'idfactura' => 'ASC']) as $factura) {
            if (false === DeclaracionIGICFactura::fromFacturaProveedor($factura, (int) $this->idmodelo)->save()) {
                return false;
            }
        }

        return true;
    }

    private function borrarConFacturas(): bool
    {
        foreach ($this->getFacturas() as $factura) {
            if (false === $factura->delete()) {
                return false;
            }
        }

        return parent::delete();
    }

    /**
     * Marca este modelo como rectificado y crea la copia rectificativa con sus facturas.
     */
    private function copiarComoRectificativo(): ?self
    {
        $this->estado = 'rectificado';
        if (false === $this->save()) {
            return null;
        }

        $nuevo = new self();
        $nuevo->tipo = $this->tipo;
        $nuevo->periodo = $this->periodo;
        $nuevo->codejercicio = $this->codejercicio;
        $nuevo->fechainicio = $this->fechainicio;
        $nuevo->fechafin = $this->fechafin;
        $nuevo->idregiva = $this->idregiva;
        $nuevo->idrectifica = $this->idmodelo;
        $nuevo->totaldevengado = $this->totaldevengado;
        $nuevo->totaldeducible = $this->totaldeducible;
        $nuevo->resultado = $this->resultado;
        $nuevo->estado = 'borrador';
        if (false === $nuevo->save()) {
            return null;
        }

        foreach ($this->getFacturas() as $factura) {
            $copia = new DeclaracionIGICFactura();
            $copia->idmodelo = $nuevo->idmodelo;
            $copia->tipofactura = $factura->tipofactura;
            $copia->idfactura = $factura->idfactura;
            $copia->codigo = $factura->codigo;
            $copia->fecha = $factura->fecha;
            $copia->cifnif = $factura->cifnif;
            $copia->nombre = $factura->nombre;
            $copia->neto = $factura->neto;
            $copia->totaligic = $factura->totaligic;
            $copia->totalrecargo = $factura->totalrecargo;
            $copia->incluida = $factura->incluida;
            if (false === $copia->save()) {
                return null;
            }
        }

        return $nuevo;
    }

    public function test(): bool
    {
        $this->tipo = Tools::noHtml($this->tipo);
        $this->periodo = Tools::noHtml($this->periodo);
        $this->codejercicio = Tools::noHtml($this->codejercicio);
        $this->estado = Tools::noHtml($this->estado);
        $this->numeroreferencia = Tools::noHtml($this->numeroreferencia ?? '');

        if (empty($this->tipo) || !in_array($this->tipo, ['420', '425'])) {
            Tools::log()->error('tipo-modelo-invalido');
            return false;
        }

        if (empty($this->periodo)) {
            Tools::log()->error('periodo-requerido');
            return false;
        }

        if (empty($this->codejercicio)) {
            Tools::log()->error('ejercicio-requerido');
            return false;
        }

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'ListDeclaracionIGIC'): string
    {
        return parent::url($type, $list);
    }
}
