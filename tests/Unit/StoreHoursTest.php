<?php

namespace Tests\Unit;

use App\Models\Store;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class StoreHoursTest extends TestCase
{
    public function test_a_branch_without_hours_is_always_open(): void
    {
        $this->assertTrue((new Store)->isOpenAt($this->manila('03:00')));
        $this->assertNull((new Store)->nextOpeningAt($this->manila('03:00')));
    }

    public function test_day_hours_open_at_opening_and_close_at_closing_in_manila_time(): void
    {
        $store = new Store(['opening_time' => '08:00', 'closing_time' => '20:00']);

        $this->assertFalse($store->isOpenAt($this->manila('07:59')));
        $this->assertTrue($store->isOpenAt($this->manila('08:00')));
        $this->assertTrue($store->isOpenAt($this->manila('19:59')));
        $this->assertFalse($store->isOpenAt($this->manila('20:00')));
        // 00:30 UTC is 08:30 in Manila.
        $this->assertTrue($store->isOpenAt(CarbonImmutable::parse('2026-11-16 00:30', 'UTC')));
    }

    public function test_overnight_hours_span_midnight(): void
    {
        $store = new Store(['opening_time' => '20:00', 'closing_time' => '06:00']);

        $this->assertTrue($store->isOpenAt($this->manila('23:00')));
        $this->assertTrue($store->isOpenAt($this->manila('05:59')));
        $this->assertFalse($store->isOpenAt($this->manila('06:00')));
        $this->assertFalse($store->isOpenAt($this->manila('12:00')));
    }

    public function test_next_opening_is_later_today_or_tomorrow(): void
    {
        $store = new Store(['opening_time' => '08:00', 'closing_time' => '20:00']);

        $this->assertSame('2026-11-16 08:00', $store->nextOpeningAt($this->manila('06:00'))->format('Y-m-d H:i'));
        $this->assertSame('2026-11-17 08:00', $store->nextOpeningAt($this->manila('21:00'))->format('Y-m-d H:i'));
        $this->assertNull($store->nextOpeningAt($this->manila('09:00')));
    }

    private function manila(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-11-16 {$time}", 'Asia/Manila');
    }
}
