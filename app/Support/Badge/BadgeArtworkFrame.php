<?php

namespace App\Support\Badge;

use Illuminate\Support\Facades\Cache;

/**
 * Wyrównuje odznaki o różnym marginesie w pliku PNG.
 * Środek to środek tarczy (największe pełne koło rysunku), nie obwiednia
 * lotki albo wieńca, które wystają poza emblemat.
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

        $key = 'badge-artwork:v2:'.md5($absolute).':'.filemtime($absolute);

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
        $opaque = array_fill(0, $canvasHeight, array_fill(0, $canvasWidth, false));
        $found = false;

        for ($y = 0; $y < $canvasHeight; $y++) {
            for ($x = 0; $x < $canvasWidth; $x++) {
                $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
                if ($alpha >= self::ALPHA_LIMIT) {
                    continue;
                }
                $opaque[$y][$x] = true;
                $found = true;
            }
        }
        imagedestroy($image);

        $center = $found ? self::emblemCenter($opaque, $canvasWidth, $canvasHeight) : null;
        if ($center === null || $canvasWidth < 1 || $canvasHeight < 1) {
            return self::identity();
        }

        [$centerX, $centerY] = $center;
        $extent = 0.0;
        for ($y = 0; $y < $canvasHeight; $y++) {
            for ($x = 0; $x < $canvasWidth; $x++) {
                if (! $opaque[$y][$x]) {
                    continue;
                }
                $extent = max($extent, abs($x - $centerX), abs($y - $centerY));
            }
        }
        if ($extent < 1) {
            return self::identity();
        }

        $span = ($extent * 2) / self::FILL;

        return [
            'width' => round(100 * $canvasWidth / $span, 2),
            'height' => round(100 * $canvasHeight / $span, 2),
            'left' => round(100 * (0.5 - $centerX / $span), 2),
            'top' => round(100 * (0.5 - $centerY / $span), 2),
        ];
    }

    /**
     * Środek największego pełnego koła w rysunku — środek tarczy, nie lotki.
     *
     * @param  array<int, array<int, bool>>  $opaque
     * @return array{0: float, 1: float}|null
     */
    private static function emblemCenter(array $opaque, int $width, int $height): ?array
    {
        $inf = 999999;
        $dist = [];
        for ($y = 0; $y < $height; $y++) {
            $dist[$y] = array_fill(0, $width, 0);
            for ($x = 0; $x < $width; $x++) {
                if ($opaque[$y][$x]) {
                    $dist[$y][$x] = $inf;
                }
            }
        }

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! $opaque[$y][$x]) {
                    continue;
                }
                $best = $dist[$y][$x];
                if ($y > 0) {
                    $best = min($best, $dist[$y - 1][$x] + 1);
                }
                if ($x > 0) {
                    $best = min($best, $dist[$y][$x - 1] + 1);
                }
                $dist[$y][$x] = $best;
            }
        }
        for ($y = $height - 1; $y >= 0; $y--) {
            for ($x = $width - 1; $x >= 0; $x--) {
                if (! $opaque[$y][$x]) {
                    continue;
                }
                $best = $dist[$y][$x];
                if ($y + 1 < $height) {
                    $best = min($best, $dist[$y + 1][$x] + 1);
                }
                if ($x + 1 < $width) {
                    $best = min($best, $dist[$y][$x + 1] + 1);
                }
                $dist[$y][$x] = $best;
            }
        }

        $bestDistance = 0;
        $sumX = 0;
        $sumY = 0;
        $count = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($dist[$y][$x] > $bestDistance) {
                    $bestDistance = $dist[$y][$x];
                    $sumX = $x;
                    $sumY = $y;
                    $count = 1;
                } elseif ($bestDistance > 0 && $dist[$y][$x] === $bestDistance) {
                    $sumX += $x;
                    $sumY += $y;
                    $count++;
                }
            }
        }

        if ($count === 0) {
            return null;
        }

        return [$sumX / $count, $sumY / $count];
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
