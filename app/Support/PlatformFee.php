<?php

namespace App\Support;

class PlatformFee
{
    /**
     * The fee on an amount, rounded half up to the centavo.
     *
     * bcmath truncates, so half a centavo is added before cutting to two places.
     * Amounts here are never negative, which is what makes that trick correct.
     */
    public static function on(string $amount, string $rate): string
    {
        return bcadd(bcmul($amount, $rate, 6), '0.005', 2);
    }
}
