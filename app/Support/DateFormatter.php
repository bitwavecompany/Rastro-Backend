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

    /**
     * Formatea fecha y hora exacta convirtiendo a la zona horaria local de visualización (ej. "13 oct 2026, 21:39").
     */
    public static function formatDateTime(?CarbonInterface $date, ?string $timezone = null): string
    {
        if ($date === null) {
            return '';
        }

        $tz = $timezone ?? (function_exists('config') ? (string) config('app.display_timezone', 'America/Guayaquil') : 'America/Guayaquil');

        return strtolower(
            str_replace(
                '.',
                '',
                $date->copy()->timezone($tz)->locale('es')->isoFormat('D MMM YYYY, HH:mm')
            )
        );
    }
}
