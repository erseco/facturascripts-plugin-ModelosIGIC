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

use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;

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
        $this->borrarIbanGuardado();
    }

    public function uninstall(): void
    {
        // Limpieza al desinstalar
    }

    /**
     * Las versiones anteriores guardaban el IBAN en la configuración, que no va cifrada.
     */
    private function borrarIbanGuardado(): void
    {
        $cambios = false;
        foreach (Empresas::all() as $empresa) {
            $clave = 'fichero-atc-' . $empresa->idempresa;
            $datos = json_decode((string) Tools::settings('modelosigic', $clave, ''), true);
            if (is_array($datos) && array_key_exists('iban', $datos)) {
                unset($datos['iban']);
                Tools::settingsSet('modelosigic', $clave, json_encode($datos));
                $cambios = true;
            }
        }

        if ($cambios) {
            Tools::settingsSave();
        }
    }
}
