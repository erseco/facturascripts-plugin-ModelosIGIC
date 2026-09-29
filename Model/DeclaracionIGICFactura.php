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
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;

/**
 * Modelo para almacenar las facturas incluidas en cada modelo fiscal.
 *
 * Permite hacer seguimiento de qué facturas se presentaron en cada modelo
 * y facilita la generación de modelos rectificativos.
 */
class DeclaracionIGICFactura extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var int ID del modelo fiscal */
    public $idmodelo;

    /** @var string Tipo de factura: "cliente" o "proveedor" */
    public $tipofactura;

    /** @var int ID de la factura */
    public $idfactura;

    /** @var string Código de la factura */
    public $codigo;

    /** @var string Fecha de la factura */
    public $fecha;

    /** @var string CIF/NIF del tercero */
    public $cifnif;

    /** @var string Nombre del tercero */
    public $nombre;

    /** @var float Base imponible (neto) */
    public $neto;

    /** @var float Total IGIC */
    public $totaligic;

    /** @var float Total recargo de equivalencia */
    public $totalrecargo;

    /** @var bool Si la factura está incluida en el modelo */
    public $incluida;

    public function clear(): void
    {
        parent::clear();
        $this->neto = 0.0;
        $this->totaligic = 0.0;
        $this->totalrecargo = 0.0;
        $this->incluida = true;
    }

    public function install(): string
    {
        // dependencias de las claves foráneas
        new DeclaracionIGIC();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'declaraciones_igic_facturas';
    }

    /**
     * Obtiene la factura original.
     *
     * @return FacturaCliente|FacturaProveedor|null
     */
    public function getFactura()
    {
        $factura = match ($this->tipofactura) {
            'cliente' => new FacturaCliente(),
            'proveedor' => new FacturaProveedor(),
            default => null,
        };

        return $factura !== null && $factura->load($this->idfactura) ? $factura : null;
    }

    /**
     * Obtiene el modelo fiscal al que pertenece esta factura.
     */
    public function getModelo(): ?DeclaracionIGIC
    {
        $modelo = new DeclaracionIGIC();
        return $modelo->load($this->idmodelo) ? $modelo : null;
    }

    /**
     * Calcula el total de la factura (neto + igic + recargo).
     */
    public function total(): float
    {
        return $this->neto + $this->totaligic + $this->totalrecargo;
    }

    /**
     * Devuelve la URL de la factura original.
     */
    public function urlFactura(): string
    {
        $factura = $this->getFactura();
        return $factura ? $factura->url() : '#';
    }

    /**
     * Crea un registro desde una factura de cliente.
     */
    public static function fromFacturaCliente(FacturaCliente $factura, int $idModelo): self
    {
        $mf = new self();
        $mf->idmodelo = $idModelo;
        $mf->tipofactura = 'cliente';
        $mf->idfactura = $factura->idfactura;
        $mf->codigo = $factura->codigo;
        $mf->fecha = $factura->fecha;
        $mf->cifnif = $factura->cifnif;
        $mf->nombre = $factura->nombrecliente;
        $mf->neto = $factura->neto;
        $mf->totaligic = $factura->totaliva;
        $mf->totalrecargo = $factura->totalrecargo;
        $mf->incluida = true;
        return $mf;
    }

    /**
     * Crea un registro desde una factura de proveedor.
     */
    public static function fromFacturaProveedor(FacturaProveedor $factura, int $idModelo): self
    {
        $mf = new self();
        $mf->idmodelo = $idModelo;
        $mf->tipofactura = 'proveedor';
        $mf->idfactura = $factura->idfactura;
        $mf->codigo = $factura->codigo;
        $mf->fecha = $factura->fecha;
        $mf->cifnif = $factura->cifnif;
        $mf->nombre = $factura->nombre;
        $mf->neto = $factura->neto;
        $mf->totaligic = $factura->totaliva;
        $mf->totalrecargo = $factura->totalrecargo;
        $mf->incluida = true;
        return $mf;
    }

    public function test(): bool
    {
        $this->tipofactura = Tools::noHtml($this->tipofactura);
        $this->codigo = Tools::noHtml($this->codigo);
        $this->cifnif = Tools::noHtml($this->cifnif);
        $this->nombre = Tools::noHtml($this->nombre);

        if (empty($this->idmodelo)) {
            Tools::log()->error('modelo-requerido');
            return false;
        }

        if (empty($this->tipofactura) || !in_array($this->tipofactura, ['cliente', 'proveedor'])) {
            Tools::log()->error('tipo-factura-invalido');
            return false;
        }

        if (empty($this->idfactura)) {
            Tools::log()->error('factura-requerida');
            return false;
        }

        return parent::test();
    }
}
