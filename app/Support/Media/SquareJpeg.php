<?php

namespace App\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** Kwadratowy JPEG 512 px, ten sam kadr co awatar gracza. */
final class SquareJpeg
{
    public const SIZE = 512;

    public static function assertImage(UploadedFile $file, string $field): void
    {
        validator(
            [$field => $file],
            [$field => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048']],
            [],
            [$field => 'zdjęcie'],
        )->validate();
    }

    public static function encode(UploadedFile $file, string $field): string
    {
        $contents = file_get_contents($file->getRealPath());
        $src = $contents !== false ? @imagecreatefromstring($contents) : false;

        if ($src === false) {
            throw ValidationException::withMessages([
                $field => 'Nie udało się odczytać obrazu.',
            ]);
        }

        $width = imagesx($src);
        $height = imagesy($src);
        if ($width < 1 || $height < 1) {
            imagedestroy($src);
            throw ValidationException::withMessages([
                $field => 'Nie udało się odczytać obrazu.',
            ]);
        }

        $side = min($width, $height);
        $srcX = (int) floor(($width - $side) / 2);
        $srcY = (int) floor(($height - $side) / 2);

        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $side, $side);
        imagedestroy($src);

        ob_start();
        $written = imagejpeg($dst, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($dst);

        if ($written !== true || ! is_string($jpeg) || $jpeg === '') {
            throw ValidationException::withMessages([
                $field => 'Nie udało się zapisać obrazu.',
            ]);
        }

        return $jpeg;
    }
}
