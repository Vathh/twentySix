<?php

namespace App\Support\Badge;

use Illuminate\Support\Facades\Cache;

/**
 * Wyrównuje odznaki o różnym marginesie w pliku PNG.
 * Ramka pokazuje nieprzezroczysty rysunek w tej samej skali, niezależnie od wielkości płótna.
 */
final class BadgeArtworkFrame
{
    private const FILL = 0.92;

    /** Piksele o alfcie GD poniżej progu liczą się jako rysunek (0 = nieprzezroczyste, 127 = puste). */
    private const ALPHA_LIMIT = 64;

    /**
     * @return array{width: float, height: float, left: float, top: float}
     */
    public static function placement(string $relativePublicPath): array
    {
        $absolute = public_path($relativePublicPath);
        if (! is_file($absolute)) {
            return self::identity();
        }

        $key = 'badge-artwork:'.md5($absolute).':'.filemtime($absolute);

        return Cache::rememberForever($key, fn () => self::measure($absolute));
    }

    /**
     * @return array{width: float, height: float, left: float, top: float}
     */
    public static function measure(string $absolutePath): array
    {
        $image = imagecreatefrompng($absolutePath);
        if ($image === false) {
            return self::identity();
        }

        $canvasWidth = imagesx($image);
        $canvasHeight = imagesy($image);
        $minX = $canvasWidth;
        $minY = $canvasHeight;
        $maxX = 0;
        $maxY = 0;
        $found = false;

        for ($y = 0; $y < $canvasHeight; $y++) {
            for ($x = 0; $x < $canvasWidth; $x++) {
                $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
                if ($alpha >= self::ALPHA_LIMIT) {
                    continue;
                }
                $found = true;
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
        imagedestroy($image);

        if (! $found || $canvasWidth < 1 || $canvasHeight < 1) {
            return self::identity();
        }

        $contentWidth = $maxX - $minX + 1;
        $contentHeight = $maxY - $minY + 1;
        $span = max($contentWidth, $contentHeight) / self::FILL;
        $centerX = $minX + ($contentWidth / 2);
        $centerY = $minY + ($contentHeight / 2);

        return [
            'width' => round(100 * $canvasWidth / $span, 2),
            'height' => round(100 * $canvasHeight / $span, 2),
            'left' => round(100 * (0.5 - $centerX / $span), 2),
            'top' => round(100 * (0.5 - $centerY / $span), 2),
        ];
    }

    /**
     * @return array{width: float, height: float, left: float, top: float}
     */
    private static function identity(): array
    {
        return [
            'width' => 100.0,
            'height' => 100.0,
            'left' => 0.0,
            'top' => 0.0,
        ];
    }
}
