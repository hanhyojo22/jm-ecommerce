<?php

namespace App\Support;

use App\Models\MedicationDoseLog;
use App\Models\MedicationPlan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Works out how much of a medicine is left and when it runs out. A scheduled dose nobody marked
 * counts as taken once it is a day old: assuming adherence predicts the run-out early, which is
 * the safe side for a reorder reminder.
 */
class MedicationSupply
{
    public const TIMEZONE = 'Asia/Manila';

    public const REORDER_LEAD_DAYS = 5;

    public const COUNT_PROMPT_DAYS = 7;

    /**
     * Scheduled dose times in ($from, $to], read from the plan's "HH:MM" times in Manila and kept
     * within starts_on..ends_on. As-needed plans have no schedule.
     *
     * @return list<CarbonImmutable>
     */
    public static function slots(MedicationPlan $plan, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($plan->medicine_kind === 'as_needed' || $plan->dose_times === []) {
            return [];
        }

        $from = CarbonImmutable::instance($from);
        $to = CarbonImmutable::instance($to);
        $firstDay = $from->setTimezone(self::TIMEZONE)->startOfDay()->max(self::day($plan->starts_on));
        $lastDay = $to->setTimezone(self::TIMEZONE)->startOfDay();
        if ($plan->ends_on !== null) {
            $lastDay = $lastDay->min(self::day($plan->ends_on));
        }

        $slots = [];
        for ($day = $firstDay; $day->lte($lastDay); $day = $day->addDay()) {
            foreach ($plan->dose_times as $time) {
                [$hour, $minute] = array_map('intval', explode(':', $time));
                $slot = $day->setTime($hour, $minute);
                if ($slot->gt($from) && $slot->lte($to)) {
                    $slots[] = $slot;
                }
            }
        }

        usort($slots, fn (CarbonImmutable $a, CarbonImmutable $b): int => $a <=> $b);

        return $slots;
    }

    /**
     * @return array{units_left: float, daily_units: ?float, run_out_on: ?string, reorder_on: ?string, count_prompt_on: ?string}
     */
    public static function summary(MedicationPlan $plan, CarbonInterface $now): array
    {
        $now = CarbonImmutable::instance($now)->setTimezone(self::TIMEZONE);
        $anchoredAt = CarbonImmutable::instance($plan->anchored_at);
        $dose = (float) $plan->dose_quantity;
        $logs = $plan->doseLogs;

        $taken = $logs->filter(fn (MedicationDoseLog $log): bool => $log->status === 'taken' && $log->logged_at->gte($anchoredAt));
        $answered = $logs->filter(fn (MedicationDoseLog $log): bool => $log->scheduled_for !== null)
            ->map(fn (MedicationDoseLog $log): int => $log->scheduled_for->getTimestamp())
            ->flip();
        // A paused plan is re-anchored when paused, so nothing after that is assumed taken.
        $assumed = $plan->status === 'paused' ? 0 : collect(self::slots($plan, $anchoredAt, $now->subDay()))
            ->reject(fn (CarbonImmutable $slot): bool => $answered->has($slot->getTimestamp()))
            ->count();

        $consumed = $taken->sum(fn (MedicationDoseLog $log): float => (float) $log->quantity) + $assumed * $dose;
        $unitsLeft = round(max(0, (float) $plan->units_anchor - $consumed), 2);

        $daily = match ($plan->medicine_kind) {
            'as_needed' => self::averageDailyUse($logs->all(), $now),
            default => $dose * count($plan->dose_times),
        };

        if ($plan->medicine_kind === 'course' || $daily === null || $daily <= 0) {
            return ['units_left' => $unitsLeft, 'daily_units' => $daily, 'run_out_on' => null, 'reorder_on' => null, 'count_prompt_on' => null];
        }

        $runOut = $now->startOfDay()->addDays((int) floor($unitsLeft / $daily));

        return [
            'units_left' => $unitsLeft,
            'daily_units' => $daily,
            'run_out_on' => $runOut->toDateString(),
            'reorder_on' => $runOut->subDays(self::REORDER_LEAD_DAYS)->toDateString(),
            'count_prompt_on' => $runOut->subDays(self::COUNT_PROMPT_DAYS)->toDateString(),
        ];
    }

    /**
     * Average units a day over the last 14 days of logged as-needed doses; too few logs to tell → null.
     *
     * @param  list<MedicationDoseLog>  $logs
     */
    private static function averageDailyUse(array $logs, CarbonImmutable $now): ?float
    {
        $recent = array_filter($logs, fn (MedicationDoseLog $log): bool => $log->status === 'taken' && $log->logged_at->gte($now->subDays(14)));

        if (count($recent) < 3) {
            return null;
        }

        return array_sum(array_map(fn (MedicationDoseLog $log): float => (float) $log->quantity, $recent)) / 14;
    }

    private static function day(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString(), self::TIMEZONE);
    }
}
