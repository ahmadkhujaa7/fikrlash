<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Ixcham vaqt ko‘rinishi: "hozir", "5 daq", "3 soat", "2 kun", "5-mart", "5-mart, 2025". */
final class Time
{
    private const MONTHS = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];

    public static function short(?CarbonInterface $time): string
    {
        if (! $time) {
            return '';
        }

        $minutes = (int) $time->diffInMinutes(now());

        return match (true) {
            $minutes < 1 => 'hozir',
            $minutes < 60 => "{$minutes} daq",
            $minutes < 1440 => intdiv($minutes, 60).' soat',
            $minutes < 10080 => intdiv($minutes, 1440).' kun',
            default => self::date($time),
        };
    }

    public static function date(CarbonInterface $time): string
    {
        $label = $time->day.'-'.self::MONTHS[$time->month - 1];

        return $time->year === now()->year ? $label : "{$label}, {$time->year}";
    }

    public static function full(CarbonInterface $time): string
    {
        return self::date($time).', '.$time->format('H:i');
    }
}
