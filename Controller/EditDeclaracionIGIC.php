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
use FacturaScripts\Plugins\ModelosIGIC\Lib\ATCFileGenerator;
use FacturaScripts\Plugins\ModelosIGIC\Lib\IGICHelper;
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

    protected function execPreviousAction($action): bool
    {
        if ($action === 'download-atc') {
            $this->downloadATC();
            return false;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Genera y descarga el fichero ATC.
     */
    protected function downloadATC(): void
    {
        $code = $this->request->get('code');
        $modelo = new DeclaracionIGIC();
        if (false === $modelo->loadFromCode($code)) {
            return;
        }

        $helper = new IGICHelper();
        $generator = new ATCFileGenerator($modelo);

        $desgloseVentas = $helper->desgloseIGICVentas($modelo->fechainicio, $modelo->fechafin);
        $desgloseCompras = $helper->desgloseIGICCompras($modelo->fechainicio, $modelo->fechafin);

        $generator->setDesgloseVentas($desgloseVentas)
            ->setDesgloseCompras($desgloseCompras);

        $filename = $generator->getFilename();
        $content = $generator->generate();

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo $content;
        exit;
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
            'type' => 'link',
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
                    new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idmodelo', $idmodelo),
                    new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('tipofactura', 'cliente'),
                ];
                $view->loadData('', $where);
                break;

            case 'ListDeclaracionIGICFactura-proveedor':
                $idmodelo = $this->getViewModelValue($mvn, 'idmodelo');
                $where = [
                    new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idmodelo', $idmodelo),
                    new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('tipofactura', 'proveedor'),
                ];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
