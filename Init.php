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

namespace FacturaScripts\Plugins\ModelosIGIC;

use FacturaScripts\Core\Template\InitClass;

/**
 * Plugin para la generación de los Modelos 420 y 425 de la Agencia Tributaria Canaria.
 *
 * - Modelo 420: Autoliquidación trimestral del IGIC (Impuesto General Indirecto Canario)
 * - Modelo 425: Declaración-resumen anual del IGIC
 *
 * @see https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-420
 */
final class Init extends InitClass
{
    public function init(): void
    {
        // No se requieren extensiones para este plugin
    }

    public function update(): void
    {
        // Migraciones y actualizaciones futuras
    }

    public function uninstall(): void
    {
        // Limpieza al desinstalar
    }
}
