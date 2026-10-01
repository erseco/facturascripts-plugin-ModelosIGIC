<?php

/**
 * This file is part of ModelosIGIC plugin for FacturaScripts.
 * Copyright (C) 2026 Ernesto Serrano <info@ernesto.es>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 */

namespace FacturaScripts\Plugins\ModelosIGIC\Lib;

use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;

final class ComparativaIGIC
{
    /**
     * Builds a yearly matrix for quarterly Model 420 declarations.
     *
     * @param DeclaracionIGIC[] $declaraciones
     */
    public static function modelo420(array $declaraciones, int $desde, int $hasta): array
    {
        [$desde, $hasta] = self::normalizarRango($desde, $hasta);
        $rows = [];

        foreach ($declaraciones as $declaracion) {
            if (
                $declaracion->tipo !== '420'
                || false === in_array($declaracion->periodo, IGICHelper::PERIODOS_420, true)
            ) {
                continue;
            }

            $year = self::year($declaracion);
            if ($year < $desde || $year > $hasta) {
                continue;
            }

            if (false === isset($rows[$year])) {
                $rows[$year] = [
                    'ejercicio' => $year,
                    'periodos' => ['T1' => null, 'T2' => null, 'T3' => null, 'T4' => null],
                ];
            }

            $rows[$year]['periodos'][$declaracion->periodo] = self::totales($declaracion);
        }

        ksort($rows);
        return array_values($rows);
    }

    /**
     * Builds annual comparison rows for Model 425 declarations.
     *
     * @param DeclaracionIGIC[] $declaraciones
     */
    public static function modelo425(array $declaraciones, int $desde, int $hasta): array
    {
        [$desde, $hasta] = self::normalizarRango($desde, $hasta);
        $rows = [];

        foreach ($declaraciones as $declaracion) {
            if ($declaracion->tipo !== '425') {
                continue;
            }

            $year = self::year($declaracion);
            if ($year < $desde || $year > $hasta) {
                continue;
            }

            $rows[$year] = array_merge(['ejercicio' => $year], self::totales($declaracion));
        }

        ksort($rows);
        return array_values($rows);
    }

    private static function normalizarRango(int $desde, int $hasta): array
    {
        return $desde <= $hasta ? [$desde, $hasta] : [$hasta, $desde];
    }

    private static function totales(DeclaracionIGIC $declaracion): array
    {
        return [
            'totaldevengado' => (float) $declaracion->totaldevengado,
            'totaldeducible' => (float) $declaracion->totaldeducible,
            'resultado' => (float) $declaracion->resultado,
        ];
    }

    private static function year(DeclaracionIGIC $declaracion): int
    {
        return (int) date('Y', strtotime((string) $declaracion->fechainicio));
    }
}
