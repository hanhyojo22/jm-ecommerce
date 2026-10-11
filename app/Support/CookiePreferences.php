<?php

namespace App\Support;

use Illuminate\Http\Request;

class CookiePreferences
{
    public const VERSION = 1;

    public const COOKIE_NAME = 'jmmart_cookie_preferences';

    /** @return array{version: int, functional: bool, analytics: bool, marketing: bool}|null */
    public static function fromRequest(Request $request): ?array
    {
        $preferences = json_decode((string) $request->cookie(self::COOKIE_NAME), true);

        if (! is_array($preferences) || ($preferences['version'] ?? null) !== self::VERSION) {
            return null;
        }

        foreach (['functional', 'analytics', 'marketing'] as $category) {
            if (! isset($preferences[$category]) || ! is_bool($preferences[$category])) {
                return null;
            }
        }

        return $preferences;
    }
}
