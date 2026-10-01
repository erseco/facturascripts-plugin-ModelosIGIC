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

namespace FacturaScripts\Plugins\ModelosIGIC\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;

/**
 * Controlador para editar/visualizar un modelo fiscal.
 */
class EditDeclaracionIGIC extends EditController
{
    public function getModelClassName(): string
    {
        return 'DeclaracionIGIC';
    }

    protected function execPreviousAction($action)
    {
        if ($action === 'download-atc') {
            return $this->downloadATC();
        }

        if ($action === 'edit') {
            $this->conservarCamposNoEditables();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * En la ficha solo se editan el número de referencia y la fecha de presentación. El estado,
     * el período y los importes los gestionan las acciones del Modelo 420 y del 425: devolver a
     * borrador una declaración presentada permitiría borrarla.
     */
    protected function conservarCamposNoEditables(): void
    {
        $modelo = new DeclaracionIGIC();
        if (false === $modelo->load($this->request->queryOrInput('code', ''))) {
            return;
        }

        foreach ($modelo->toArray() as $campo => $valor) {
            if (false === in_array($campo, ['numeroreferencia', 'fechapresentacion'], true)) {
                $this->request->request->set($campo, $valor);
            }
        }
    }

    /**
     * Lleva al Modelo 420 de la declaración, donde se completan los datos del fichero para el
     * programa de ayuda de la ATC. El 425 no tiene fichero (doc/NORMATIVA.md).
     */
    protected function downloadATC(): bool
    {
        $modelo = new DeclaracionIGIC();
        if (false === $modelo->load($this->request->queryOrInput('code', ''))) {
            Tools::log()->warning('record-not-found');
            return true;
        }

        if ($modelo->tipo !== '420' || empty($modelo->idregiva)) {
            Tools::log()->warning('fichero-atc-solo-420');
            return true;
        }

        $this->redirect('Modelo420?id=' . $modelo->idregiva);
        return false;
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'declaracion-igic';
        $data['icon'] = 'fa-solid fa-file-invoice';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews(): void
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        // Botón para descargar fichero ATC
        $this->addButton($this->getMainViewName(), [
            'action' => 'download-atc',
            'icon' => 'fa-solid fa-download',
            'label' => 'descargar-atc',
            'type' => 'action',
        ]);

        // Pestaña de facturas de cliente
        $this->createViewFacturasCliente();

        // Pestaña de facturas de proveedor
        $this->createViewFacturasProveedor();
    }

    protected function createViewFacturasCliente(string $viewName = 'ListDeclaracionIGICFactura-cliente'): void
    {
        $this->addListView($viewName, 'DeclaracionIGICFactura', 'facturas-ventas', 'fa-solid fa-file-invoice');
        $this->views[$viewName]->addOrderBy(['fecha'], 'fecha', 2);
        $this->views[$viewName]->addOrderBy(['codigo'], 'codigo');
        $this->views[$viewName]->addSearchFields(['codigo', 'cifnif', 'nombre']);

        // Deshabilitar botones
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }

    protected function createViewFacturasProveedor(string $viewName = 'ListDeclaracionIGICFactura-proveedor'): void
    {
        $this->addListView($viewName, 'DeclaracionIGICFactura', 'facturas-compras', 'fa-solid fa-file-invoice-dollar');
        $this->views[$viewName]->addOrderBy(['fecha'], 'fecha', 2);
        $this->views[$viewName]->addOrderBy(['codigo'], 'codigo');
        $this->views[$viewName]->addSearchFields(['codigo', 'cifnif', 'nombre']);

        // Deshabilitar botones
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'btnDelete', false);
    }

    protected function loadData($viewName, $view): void
    {
        $mvn = $this->getMainViewName();

        switch ($viewName) {
            case 'ListDeclaracionIGICFactura-cliente':
                $idmodelo = $this->getViewModelValue($mvn, 'idmodelo');
                $where = [
                    Where::eq('idmodelo', $idmodelo),
                    Where::eq('tipofactura', 'cliente'),
                ];
                $view->loadData('', $where);
                break;

            case 'ListDeclaracionIGICFactura-proveedor':
                $idmodelo = $this->getViewModelValue($mvn, 'idmodelo');
                $where = [
                    Where::eq('idmodelo', $idmodelo),
                    Where::eq('tipofactura', 'proveedor'),
                ];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
