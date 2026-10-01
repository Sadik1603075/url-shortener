<?php

namespace App\Support;

/**
 * Minimal, dependency-free user-agent classifier for the device-breakdown
 * projection (D5-T3). Returns coarse buckets — browser / os / device_type —
 * not exact versions. Unknown or empty agents degrade to 'Unknown'/'unknown'
 * rather than throwing, so the projection never fails on a weird UA.
 */
final class UserAgentParser
{
    /**
     * @return array{browser: string, os: string, device_type: string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return ['browser' => 'Unknown', 'os' => 'Unknown', 'device_type' => 'unknown'];
        }

        return [
            'browser' => self::browser($ua),
            'os' => self::os($ua),
            'device_type' => self::deviceType($ua),
        ];
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg') => 'Edge',               // Edge (Edg/EdgA/EdgiOS) — before Chrome
            str_contains($ua, 'OPR') || str_contains($ua, 'Opera') => 'Opera', // before Chrome
            str_contains($ua, 'Firefox') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome') || str_contains($ua, 'CriOS') => 'Chrome', // before Safari
            str_contains($ua, 'Safari') => 'Safari',
            str_contains($ua, 'MSIE') || str_contains($ua, 'Trident') => 'Internet Explorer',
            default => 'Unknown',
        };
    }

    private static function os(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',        // before Linux (Android UAs contain "Linux")
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }

    private static function deviceType(string $ua): string
    {
        if (preg_match('/bot|crawler|spider|crawling/i', $ua)) {
            return 'bot';
        }

        if (str_contains($ua, 'iPad') || str_contains($ua, 'Tablet')) {
            return 'tablet';
        }

        if (str_contains($ua, 'Android')) {
            // Android phones advertise "Mobile"; Android tablets omit it.
            return str_contains($ua, 'Mobile') ? 'mobile' : 'tablet';
        }

        if (preg_match('/Mobile|iPhone|iPod/', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
