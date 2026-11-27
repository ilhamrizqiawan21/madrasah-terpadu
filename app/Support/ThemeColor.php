<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Perhitungan kontras WCAG AA dan turunan warna utama tema (T-208).
 */
final class ThemeColor
{
    public const DEFAULT_HEX = '#135B42';

    private const LIGHT_TEXT_HEX = '#FAF8F5';

    private const LIGHT_BG_HEX = '#FBFAF7';

    private const MIN_AA_RATIO = 4.5;

    public static function normalize(string $hex): string
    {
        return strtoupper(trim($hex));
    }

    public static function isValidHex(string $hex): bool
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', trim($hex)) === 1;
    }

    public static function passesWcagAa(string $hex): bool
    {
        if (! self::isValidHex($hex)) {
            return false;
        }

        return self::contrastRatio($hex, self::LIGHT_TEXT_HEX) >= self::MIN_AA_RATIO
            && self::contrastRatio($hex, self::LIGHT_BG_HEX) >= self::MIN_AA_RATIO;
    }

    public static function contrastRatio(string $hexA, string $hexB): float
    {
        $l1 = self::relativeLuminance($hexA);
        $l2 = self::relativeLuminance($hexB);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function relativeLuminance(string $hex): float
    {
        $hex = ltrim(trim($hex), '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $linear = static fn (float $c): float => $c <= 0.03928
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
    }
}
