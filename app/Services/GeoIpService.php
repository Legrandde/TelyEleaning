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
            $result = Cache::remember($cacheKey, 86400, function () use ($ip) {
                try {
                    $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city");
                    if ($response->successful()) {
                        $data = $response->json();
                        if (($data['status'] ?? '') === 'success') {
                            $code = strtoupper($data['countryCode'] ?? 'XX');
                            return [
                                'country'      => $data['country'] ?? 'Inconnu',
                                'country_code' => $code,
                                'code'         => $code,
                                'city'         => $data['city'] ?? null,
                            ];
                        }
                    }
                } catch (\Throwable) {
                    // Ignore and fallback
                }
                return ['country' => 'Inconnu', 'country_code' => 'XX', 'code' => 'XX', 'city' => null];
            });
            return $result;
        }

        // 2. Localhost / Private network: Resolve based on client timezone
        if ($timezone && isset(self::$timezoneMap[$timezone])) {
            $item = self::$timezoneMap[$timezone];
            return [
                'country'      => $item['country'],
                'country_code' => $item['code'],
                'code'         => $item['code'],
                'city'         => $item['city'],
            ];
        }

        // Fallback default for development environment
        return [
            'country'      => 'Sénégal',
            'country_code' => 'SN',
            'code'         => 'SN',
            'city'         => 'Dakar',
        ];
    }

    /**
     * Pseudonymise une IP avant stockage : dernier octet à zéro en IPv4, préfixe /48 en IPv6.
     * Suffit pour les statistiques par pays/ville sans conserver l'adresse exacte de l'utilisateur.
     */
    public static function anonymiser(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        $binaire = @inet_pton($ip);
        if ($binaire === false) {
            return null;
        }

        $masque = strlen($binaire) === 4
            ? "\xFF\xFF\xFF\x00"
            : str_repeat("\xFF", 6) . str_repeat("\x00", 10);

        return inet_ntop($binaire & $masque);
    }

    /**
     * Affichage dans l'administration : la partie hôte est masquée (IPv4 et IPv6).
     */
    public static function masquer(?string $ip): string
    {
        if (!$ip) {
            return '—';
        }

        if (str_contains($ip, ':')) {
            $blocs = explode(':', (string) self::anonymiser($ip));
            return implode(':', array_slice($blocs, 0, 3)) . ':****';
        }

        return preg_replace('/\.\d+$/', '.***', $ip) ?? '—';
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
