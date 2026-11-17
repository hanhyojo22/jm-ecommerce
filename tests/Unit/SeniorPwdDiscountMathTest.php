<?php

namespace Tests\Unit;

use App\Support\SeniorPwdDiscount;
use Tests\TestCase;

class SeniorPwdDiscountMathTest extends TestCase
{
    public function test_a_vat_registered_pharmacy_removes_vat_then_takes_twenty_percent(): void
    {
        config()->set('marketplace.senior_pwd.vat_registered', true);

        // ₱100 → ₱89.29 without VAT → less ₱17.86 → pays ₱71.43.
        $this->assertSame('28.57', SeniorPwdDiscount::on('100.00'));
        // ₱6.50 → ₱5.80 → less ₱1.16 → pays ₱4.64.
        $this->assertSame('1.86', SeniorPwdDiscount::on('6.50'));
        $this->assertSame('0.00', SeniorPwdDiscount::on('0.00'));
    }

    public function test_a_non_vat_pharmacy_takes_a_plain_twenty_percent(): void
    {
        config()->set('marketplace.senior_pwd.vat_registered', false);

        $this->assertSame('20.00', SeniorPwdDiscount::on('100.00'));
        $this->assertSame('1.30', SeniorPwdDiscount::on('6.50'));
        $this->assertSame('0.01', SeniorPwdDiscount::on('0.05'));
    }

    public function test_the_card_number_is_masked_to_its_last_four(): void
    {
        $this->assertSame('•••••5678', SeniorPwdDiscount::mask('1234-5678'));
        $this->assertSame('123', SeniorPwdDiscount::mask('123'));
        $this->assertNull(SeniorPwdDiscount::mask(null));
    }
}
