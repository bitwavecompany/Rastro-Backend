<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

final class DateFormatter
{
    /**
     * Formatea una fecha bajo el estándar PRD Regla 11: "dd mmm yyyy" (ej. "10 oct 2026").
     */
    public static function format(?CarbonInterface $date): string
    {
        if ($date === null) {
            return '';
        }

        return strtolower(str_replace('.', '', $date->locale('es')->isoFormat('D MMM YYYY')));
    }
}
