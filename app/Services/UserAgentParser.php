<?php

namespace App\Services;

class UserAgentParser
{
    public static function parse(?string $ua): array
    {
        $ua = $ua ?? '';
        $os = 'Inconnu';
        $browser = 'Inconnu';
        $device = 'desktop';

        // ─── OS Detection ───
        if (preg_match('/windows nt 10/i', $ua)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/windows nt 6\.3/i', $ua)) {
            $os = 'Windows 8.1';
        } elseif (preg_match('/windows nt 6\.1/i', $ua)) {
            $os = 'Windows 7';
        } elseif (preg_match('/windows nt/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/android/i', $ua)) {
            $os = 'Android';
            $device = 'mobile';
        } elseif (preg_match('/iphone/i', $ua)) {
            $os = 'iOS (iPhone)';
            $device = 'mobile';
        } elseif (preg_match('/ipad/i', $ua)) {
            $os = 'iOS (iPad)';
            $device = 'tablet';
        } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/cros/i', $ua)) {
            $os = 'Chrome OS';
        } elseif (preg_match('/linux/i', $ua)) {
            $os = 'Linux';
        }

        // ─── Browser Detection ───
        if (preg_match('/edg/i', $ua)) {
            $browser = 'Microsoft Edge';
        } elseif (preg_match('/opr\//i', $ua) || preg_match('/opera/i', $ua)) {
            $browser = 'Opera';
        } elseif (preg_match('/chrome|crios/i', $ua)) {
            $browser = 'Google Chrome';
        } elseif (preg_match('/firefox|fxios/i', $ua)) {
            $browser = 'Mozilla Firefox';
        } elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) {
            $browser = 'Apple Safari';
        }

        // ─── Device refinement ───
        if (preg_match('/tablet|ipad/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/mobile|android|iphone/i', $ua)) {
            $device = 'mobile';
        }

        return [$os, $browser, $device];
    }
}
