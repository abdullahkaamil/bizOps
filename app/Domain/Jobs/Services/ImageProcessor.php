<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Services;

use RuntimeException;

/**
 * Native GD image processing: validates the payload is a real image, fixes EXIF
 * orientation, downscales, and re-encodes as JPEG (which also strips metadata).
 * Produces a compressed main image and a thumbnail. No external dependency.
 */
class ImageProcessor
{
    public function isSupported(): bool
    {
        return extension_loaded('gd');
    }

    /**
     * @return array{image: string, thumbnail: string, mime: string, width: int, height: int}
     */
    public function process(string $bytes, int $maxWidth = 1600, int $thumbWidth = 400, int $quality = 82): array
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }

        $source = $this->applyExifOrientation($source, $bytes);

        $main = $this->resize($source, $maxWidth);
        $thumb = $this->resize($source, $thumbWidth);

        imagedestroy($source);

        $result = [
            'image' => $this->encode($main),
            'thumbnail' => $this->encode($thumb),
            'mime' => 'image/jpeg',
            'width' => imagesx($main),
            'height' => imagesy($main),
        ];

        imagedestroy($main);
        imagedestroy($thumb);

        return $result;
    }

    private function resize(\GdImage $source, int $maxWidth): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= $maxWidth) {
            $newWidth = $width;
            $newHeight = $height;
        } else {
            $newWidth = $maxWidth;
            $newHeight = (int) round($height * ($maxWidth / $width));
        }

        $newWidth = max(1, $newWidth);
        $newHeight = max(1, $newHeight);

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        // Flatten any alpha onto white so JPEG output looks correct.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        if ($white !== false) {
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $white);
        }
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private function encode(\GdImage $image, int $quality = 82): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }

    private function applyExifOrientation(\GdImage $image, string $bytes): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? null) : null;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }
}
