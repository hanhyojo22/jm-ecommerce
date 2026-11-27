<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Philippine provinces, cities/municipalities and barangays for the address dropdowns.
 *
 * Built from the PSA PSGC list (fetched from psgc.cloud in November 2026). The NCR cities
 * sit under "Metro Manila", and "City of Mandaue" reads "Mandaue City", the way people
 * write it. Saved addresses stay free text, so a name missing here is still accepted.
 */
class PhilippineLocations
{
    /** @var array<string, array<string, list<string>>>|null */
    private static ?array $tree = null;

    /**
     * @return array<string, array<string, list<string>>> province => city => barangays
     */
    public static function tree(): array
    {
        return self::$tree ??= json_decode(
            (string) file_get_contents(database_path('ph-locations.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return list<array{name: string, cities: list<string>}>
     */
    public static function provinces(): array
    {
        return collect(self::tree())
            ->map(fn (array $cities, string $province): array => ['name' => $province, 'cities' => array_map('strval', array_keys($cities))])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function barangays(string $province, string $city): array
    {
        return self::find(self::find(self::tree(), $province) ?? [], $city) ?? [];
    }

    /**
     * The form a name is compared in: "Mandaue", "Mandaue City" and "City of Mandaue" are one place.
     */
    public static function key(string $name): string
    {
        $name = Str::lower(Str::ascii((string) preg_replace('/\(.*?\)/', '', $name)));

        return (string) preg_replace('/[^a-z0-9]/', '', (string) preg_replace('/^city of |\bcity$/', '', trim($name)));
    }

    /**
     * @param  array<array-key, mixed>  $named
     * @return array<array-key, mixed>|null
     */
    private static function find(array $named, string $name): ?array
    {
        $key = self::key($name);

        foreach ($named as $candidate => $value) {
            if (self::key((string) $candidate) === $key) {
                return $value;
            }
        }

        return null;
    }
}
