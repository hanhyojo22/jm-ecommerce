<?php

namespace App\Support;

class BuyerLink
{
    /**
     * The closing line of a buyer email: a link to the web page while the web store is open,
     * otherwise a pointer to the mobile app, because the web page would only answer 410.
     */
    public static function emailLine(string $label, string $url, string $appText = 'Open the JMMart app to see your order.'): string
    {
        return config('marketplace.web_store.enabled') ? "{$label}: {$url}" : $appText;
    }
}
