<?php

namespace Tests\Unit;

use App\Models\MedicationDoseLog;
use App\Models\MedicationPlan;
use App\Support\MedicationSupply;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MedicationSupplyTest extends TestCase
{
    public function test_maintenance_run_out_counts_assumed_doses(): void
    {
        $plan = $this->plan(['units_anchor' => '30', 'anchored_at' => $this->manila('2026-12-05 12:00')]);

        $summary = MedicationSupply::summary($plan, $this->manila('2026-12-10 12:00'));

        $this->assertSame(22.0, $summary['units_left']);
        $this->assertSame(2.0, $summary['daily_units']);
        $this->assertSame('2026-12-21', $summary['run_out_on']);
        $this->assertSame('2026-12-16', $summary['reorder_on']);
        $this->assertSame('2026-12-14', $summary['count_prompt_on']);
    }

    public function test_skipped_doses_are_not_consumed(): void
    {
        $plan = $this->plan(['units_anchor' => '30', 'anchored_at' => $this->manila('2026-12-05 12:00')], [
            $this->log('skipped', '2026-12-06 08:00'),
            $this->log('skipped', '2026-12-06 20:00'),
        ]);

        $this->assertSame(24.0, MedicationSupply::summary($plan, $this->manila('2026-12-10 12:00'))['units_left']);
    }

    public function test_decimal_doses_in_millilitres(): void
    {
        $logs = [];
        foreach (['2026-12-08', '2026-12-09'] as $day) {
            foreach (['08:00', '14:00', '20:00'] as $time) {
                $logs[] = $this->log('taken', "$day $time", '5');
            }
        }
        $plan = $this->plan([
            'units_anchor' => '60', 'dose_quantity' => '5', 'dose_unit' => 'mL', 'dose_times' => ['08:00', '14:00', '20:00'],
            'anchored_at' => $this->manila('2026-12-08 07:00'), 'starts_on' => '2026-12-08',
        ], $logs);

        $summary = MedicationSupply::summary($plan, $this->manila('2026-12-10 07:00'));

        $this->assertSame(30.0, $summary['units_left']);
        $this->assertSame('2026-12-12', $summary['run_out_on']);
    }

    public function test_as_needed_uses_logged_average_and_needs_three_logs(): void
    {
        $now = $this->manila('2026-12-15 12:00');
        $attributes = ['medicine_kind' => 'as_needed', 'dose_times' => [], 'units_anchor' => '20', 'anchored_at' => $this->manila('2026-12-01 00:00')];

        $twoLogs = $this->plan($attributes, [$this->log('taken', '2026-12-10 09:00', '1', false), $this->log('taken', '2026-12-11 09:00', '1', false)]);
        $this->assertNull(MedicationSupply::summary($twoLogs, $now)['run_out_on']);

        $sevenLogs = $this->plan($attributes, array_map(fn (int $day) => $this->log('taken', sprintf('2026-12-%02d 09:00', $day), '1', false), range(5, 11)));
        $summary = MedicationSupply::summary($sevenLogs, $now);

        $this->assertSame(13.0, $summary['units_left']);
        $this->assertSame(0.5, $summary['daily_units']);
        $this->assertSame('2027-01-10', $summary['run_out_on']);
    }

    public function test_course_has_no_run_out(): void
    {
        $plan = $this->plan(['medicine_kind' => 'course', 'ends_on' => '2026-12-11', 'units_anchor' => '21', 'anchored_at' => $this->manila('2026-12-05 07:00')]);

        $summary = MedicationSupply::summary($plan, $this->manila('2026-12-06 12:00'));

        $this->assertNull($summary['run_out_on']);
        $this->assertNull($summary['reorder_on']);
        $this->assertNull($summary['count_prompt_on']);
    }

    public function test_slots_are_computed_in_manila_time(): void
    {
        $plan = $this->plan(['dose_times' => ['00:30'], 'starts_on' => '2026-12-01']);

        $slots = MedicationSupply::slots($plan, CarbonImmutable::parse('2026-11-30T00:00:00Z'), CarbonImmutable::parse('2026-12-01T00:00:00Z'));

        $this->assertCount(1, $slots);
        $this->assertSame('2026-11-30T16:30:00+00:00', $slots[0]->utc()->toIso8601String());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<MedicationDoseLog>  $logs
     */
    private function plan(array $attributes, array $logs = []): MedicationPlan
    {
        $plan = new MedicationPlan([
            'product_name' => 'Amlodipine 5 mg Tablet', 'medicine_kind' => 'maintenance', 'dose_quantity' => '1', 'dose_unit' => 'tablet',
            'dose_times' => ['08:00', '20:00'], 'starts_on' => '2026-12-01', 'units_anchor' => '30', 'anchored_at' => $this->manila('2026-12-01 00:00'),
            ...$attributes,
        ]);

        return $plan->setRelation('doseLogs', collect($logs));
    }

    private function log(string $status, string $manilaTime, string $quantity = '1', bool $scheduled = true): MedicationDoseLog
    {
        return new MedicationDoseLog([
            'status' => $status, 'quantity' => $quantity, 'logged_at' => $this->manila($manilaTime),
            'scheduled_for' => $scheduled ? $this->manila($manilaTime) : null,
        ]);
    }

    private function manila(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'Asia/Manila');
    }
}
