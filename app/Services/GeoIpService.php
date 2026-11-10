<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoIpService
{
    /**
     * Map common African and international timezones to Country & Code & City
     */
    private static array $timezoneMap = [
        'Africa/Dakar'       => ['country' => 'Sénégal', 'code' => 'SN', 'city' => 'Dakar'],
        'Africa/Abidjan'     => ['country' => 'Côte d\'Ivoire', 'code' => 'CI', 'city' => 'Abidjan'],
        'Africa/Conakry'     => ['country' => 'Guinée', 'code' => 'GN', 'city' => 'Conakry'],
        'Africa/Bamako'      => ['country' => 'Mali', 'code' => 'ML', 'city' => 'Bamako'],
        'Africa/Ouagadougou' => ['country' => 'Burkina Faso', 'code' => 'BF', 'city' => 'Ouagadougou'],
        'Africa/Douala'      => ['country' => 'Cameroun', 'code' => 'CM', 'city' => 'Douala'],
        'Africa/Lome'        => ['country' => 'Togo', 'code' => 'TG', 'city' => 'Lomé'],
        'Africa/Cotonou'     => ['country' => 'Bénin', 'code' => 'BJ', 'city' => 'Cotonou'],
        'Africa/Niamey'      => ['country' => 'Niger', 'code' => 'NE', 'city' => 'Niamey'],
        'Africa/Casablanca'  => ['country' => 'Maroc', 'code' => 'MA', 'city' => 'Casablanca'],
        'Europe/Paris'       => ['country' => 'France', 'code' => 'FR', 'city' => 'Paris'],
        'Europe/Brussels'    => ['country' => 'Belgique', 'code' => 'BE', 'city' => 'Bruxelles'],
        'America/Montreal'   => ['country' => 'Canada', 'code' => 'CA', 'city' => 'Montréal'],
        'America/Toronto'    => ['country' => 'Canada', 'code' => 'CA', 'city' => 'Toronto'],
        'America/New_York'   => ['country' => 'États-Unis', 'code' => 'US', 'city' => 'New York'],
    ];

    /**
     * Resolve Country, CountryCode, and City for an IP or Timezone
     */
    public static function resolve(?string $ip, ?string $timezone = null): array
    {
        $ip = $ip ?? '127.0.0.1';

        // 1. If public IP, perform cached GeoIP lookup
        if (self::isPublicIp($ip)) {
            $cacheKey = "geoip_{$ip}";
            return Cache::remember($cacheKey, 86400, function () use ($ip) {
                try {
                    $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city");
                    if ($response->successful()) {
                        $data = $response->json();
                        if (($data['status'] ?? '') === 'success') {
                            return [
                                'country'      => $data['country'] ?? 'Inconnu',
                                'country_code' => strtoupper($data['countryCode'] ?? 'XX'),
                                'city'         => $data['city'] ?? null,
                            ];
                        }
                    }
                } catch (\Throwable) {
                    // Ignore and fallback
                }
                return ['country' => 'Inconnu', 'country_code' => 'XX', 'city' => null];
            });
        }

        // 2. Localhost / Private network: Resolve based on client timezone
        if ($timezone && isset(self::$timezoneMap[$timezone])) {
            return self::$timezoneMap[$timezone];
        }

        // Fallback default for development environment
        return [
            'country'      => 'Sénégal',
            'country_code' => 'SN',
            'city'         => 'Dakar',
        ];
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
