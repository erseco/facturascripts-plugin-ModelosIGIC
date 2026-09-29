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

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Controlador para listar los modelos fiscales presentados.
 *
 * Muestra el historial de modelos 420 y 425 con filtros por tipo, período,
 * ejercicio y estado.
 */
class ListDeclaracionIGIC extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'declaraciones-igic';
        $data['icon'] = 'fa-solid fa-file-invoice';
        $data['showonmenu'] = true;
        return $data;
    }

    protected function createViews(): void
    {
        $this->createViewDeclaracionIGIC();
    }

    protected function createViewDeclaracionIGIC(string $viewName = 'ListDeclaracionIGIC'): void
    {
        $this->addView($viewName, 'DeclaracionIGIC', 'declaraciones-igic', 'fa-solid fa-file-invoice');
        $this->addOrderBy($viewName, ['fechacreacion'], 'fecha', 2);
        $this->addOrderBy($viewName, ['codejercicio', 'periodo'], 'ejercicio');
        $this->addOrderBy($viewName, ['tipo', 'periodo'], 'tipo');
        $this->addOrderBy($viewName, ['resultado'], 'resultado');

        $this->addSearchFields($viewName, ['tipo', 'periodo', 'codejercicio', 'numeroreferencia']);

        // Filtro por tipo de modelo
        $tipoValues = [
            ['code' => '', 'description' => '------'],
            ['code' => '420', 'description' => 'Modelo 420'],
            ['code' => '425', 'description' => 'Modelo 425'],
        ];
        $this->addFilterSelect($viewName, 'tipo', 'tipo', 'tipo', $tipoValues);

        // Filtro por período
        $periodoValues = [
            ['code' => '', 'description' => '------'],
            ['code' => 'T1', 'description' => 'T1 - Primer trimestre'],
            ['code' => 'T2', 'description' => 'T2 - Segundo trimestre'],
            ['code' => 'T3', 'description' => 'T3 - Tercer trimestre'],
            ['code' => 'T4', 'description' => 'T4 - Cuarto trimestre'],
            ['code' => 'ANUAL', 'description' => 'Anual'],
        ];
        $this->addFilterSelect($viewName, 'periodo', 'periodo', 'periodo', $periodoValues);

        // Filtro por ejercicio
        $ejercicioValues = $this->codeModel->all('ejercicios', 'codejercicio', 'nombre');
        $this->addFilterSelect($viewName, 'codejercicio', 'ejercicio', 'codejercicio', $ejercicioValues);

        // Filtro por estado
        $estadoValues = [
            ['code' => '', 'description' => '------'],
            ['code' => 'borrador', 'description' => 'Borrador'],
            ['code' => 'presentado', 'description' => 'Presentado'],
            ['code' => 'rectificado', 'description' => 'Rectificado'],
        ];
        $this->addFilterSelect($viewName, 'estado', 'estado', 'estado', $estadoValues);

        // Filtro si es rectificativo
        $this->addFilterCheckbox($viewName, 'rectificativo', 'modelo-rectificativo', 'idrectifica', 'IS NOT', null);
    }
}
