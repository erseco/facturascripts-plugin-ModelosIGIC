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

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;
use FacturaScripts\Core\Tools;

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
        $factura = new DeclaracionIGICFactura();
        return $factura->all([
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idmodelo', $this->idmodelo),
        ]);
    }

    /**
     * Obtiene las facturas de cliente incluidas en este modelo.
     *
     * @return DeclaracionIGICFactura[]
     */
    public function getFacturasCliente(): array
    {
        $factura = new DeclaracionIGICFactura();
        return $factura->all([
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idmodelo', $this->idmodelo),
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('tipofactura', 'cliente'),
        ]);
    }

    /**
     * Obtiene las facturas de proveedor incluidas en este modelo.
     *
     * @return DeclaracionIGICFactura[]
     */
    public function getFacturasProveedor(): array
    {
        $factura = new DeclaracionIGICFactura();
        return $factura->all([
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idmodelo', $this->idmodelo),
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('tipofactura', 'proveedor'),
        ]);
    }

    /**
     * Comprueba si este modelo es rectificativo.
     */
    public function esRectificativo(): bool
    {
        return $this->idrectifica !== null;
    }

    /**
     * Obtiene el modelo que rectifica este (si aplica).
     */
    public function getModeloRectificado(): ?self
    {
        if ($this->idrectifica === null) {
            return null;
        }
        return $this->get($this->idrectifica);
    }

    /**
     * Obtiene los modelos rectificativos de este modelo.
     *
     * @return self[]
     */
    public function getModelosRectificativos(): array
    {
        return $this->all([
            new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idrectifica', $this->idmodelo),
        ]);
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
     */
    public function crearRectificativo(): ?self
    {
        if ($this->estado !== 'presentado') {
            return null;
        }

        // Marcar este modelo como rectificado
        $this->estado = 'rectificado';
        if (false === $this->save()) {
            return null;
        }

        // Crear el nuevo modelo rectificativo
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

        if ($nuevo->save()) {
            // Copiar las facturas al nuevo modelo
            foreach ($this->getFacturas() as $factura) {
                $nuevaFactura = new DeclaracionIGICFactura();
                $nuevaFactura->idmodelo = $nuevo->idmodelo;
                $nuevaFactura->tipofactura = $factura->tipofactura;
                $nuevaFactura->idfactura = $factura->idfactura;
                $nuevaFactura->codigo = $factura->codigo;
                $nuevaFactura->fecha = $factura->fecha;
                $nuevaFactura->cifnif = $factura->cifnif;
                $nuevaFactura->nombre = $factura->nombre;
                $nuevaFactura->neto = $factura->neto;
                $nuevaFactura->totaligic = $factura->totaligic;
                $nuevaFactura->totalrecargo = $factura->totalrecargo;
                $nuevaFactura->incluida = $factura->incluida;
                $nuevaFactura->save();
            }
            return $nuevo;
        }

        return null;
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
