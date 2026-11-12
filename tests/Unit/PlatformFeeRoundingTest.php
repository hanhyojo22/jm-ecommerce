<?php

namespace Tests\Unit;

use App\Support\PlatformFee;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlatformFeeRoundingTest extends TestCase
{
    #[DataProvider('fees')]
    public function test_the_fee_rounds_half_up_to_the_centavo(string $amount, string $rate, string $expectedFee): void
    {
        $this->assertSame($expectedFee, PlatformFee::on($amount, $rate));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function fees(): array
    {
        return [
            'whole fee' => ['1000.00', '0.01', '10.00'],
            'zero amount' => ['0.00', '0.01', '0.00'],
            'exactly half a centavo rounds up' => ['0.50', '0.01', '0.01'],
            'just under half a centavo rounds down' => ['0.49', '0.01', '0.00'],
            'fraction above half rounds up' => ['1234.56', '0.01', '12.35'],
            'fraction below half rounds down' => ['1234.40', '0.01', '12.34'],
            'half-percent rate carries into the next peso' => ['199.90', '0.005', '1.00'],
            'large order stays exact' => ['9999999999.99', '0.01', '100000000.00'],
        ];
    }
}
