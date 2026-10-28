<?php

namespace App\Services\Mao;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

/**
 * Renders the projected regular KADIWA date pattern.
 *
 * This is deliberately a PROJECTION, not a schedule. It never asserts that an event exists,
 * and it never supplies a venue, hours, capacity, fee, deadline or coordinator. Every date is
 * returned with kind "projected" and confirmed false so no consumer can mistake it for an
 * authorized event, and producers can only apply to a separately published event record.
 */
final class KadiwaScheduleProjection
{
    public function projected(): array
    {
        $config = config('kadiwa.projected_schedule');
        abort_unless(is_array($config), 503, 'KADIWA projection configuration is unavailable.');

        $timezone = (string) ($config['timezone'] ?? '');
        $ordinal = (int) ($config['ordinal'] ?? 0);
        $isoWeekday = (int) ($config['iso_weekday'] ?? 0);
        $offset = (int) ($config['following_day_offset'] ?? 0);
        $occurrences = (int) ($config['occurrences'] ?? 0);
        $notice = (string) ($config['notice'] ?? '');

        abort_unless($isoWeekday >= 1 && $isoWeekday <= 7, 503, 'KADIWA projection rule is invalid.');
        abort_unless($ordinal >= 1 && $ordinal <= 5, 503, 'KADIWA projection rule is invalid.');
        abort_unless($occurrences >= 1 && $occurrences <= 12, 503, 'KADIWA projection count is invalid.');
        abort_unless($offset >= 0 && $offset <= 7, 503, 'KADIWA projection rule is invalid.');

        try {
            $zone = new DateTimeZone($timezone);
        } catch (Throwable) {
            abort(503, 'KADIWA projection timezone is unavailable.');
        }

        $today = CarbonImmutable::now($zone)->startOfDay();
        $month = $today->startOfMonth();
        $nth = $this->nth($month, $ordinal, $isoWeekday);
        if ($nth->lessThan($today)) {
            $month = $month->addMonthNoOverflow()->startOfMonth();
            $nth = $this->nth($month, $ordinal, $isoWeekday);
        }

        $dates = [];
        for ($i = 0; $i < $occurrences; $i++) {
            foreach (array_values(array_unique([0, $offset])) as $dayOffset) {
                $dates[] = [
                    'date' => $nth->addDays($dayOffset)->format('Y-m-d'),
                    'timezone' => $timezone,
                    'kind' => 'projected',
                    'confirmed' => false,
                ];
            }
            $month = $month->addMonthNoOverflow()->startOfMonth();
            $nth = $this->nth($month, $ordinal, $isoWeekday);
        }

        return [
            'rule' => [
                'ordinal' => $ordinal,
                'iso_weekday' => $isoWeekday,
                'following_day_offset' => $offset,
                'timezone' => $timezone,
            ],
            'dates' => $dates,
            'notice' => $notice,
        ];
    }

    /** The nth ISO weekday of the given month, at local midnight. */
    private function nth(CarbonImmutable $monthStart, int $ordinal, int $isoWeekday): CarbonImmutable
    {
        $shift = ($isoWeekday - (int) $monthStart->dayOfWeekIso + 7) % 7;

        return $monthStart->addDays($shift + 7 * ($ordinal - 1))->startOfDay();
    }
}