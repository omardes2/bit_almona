<?php

namespace App\Services\Media;

use GdImage;

class GdImageProcessor implements ImageProcessor
{
    public function optimize(string $contents, string $extension, int $maxSize): ?string
    {
        $image = $this->load($contents);

        if ($image === null) {
            return null;
        }

        $image = $this->fit($image, $maxSize);

        return $this->encode($image, $extension);
    }

    public function thumbnail(string $contents, int $size): ?string
    {
        $image = $this->load($contents);

        if ($image === null) {
            return null;
        }

        return $this->encode($this->fit($image, $size), 'webp');
    }

    private function load(string $contents): ?GdImage
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        return $image instanceof GdImage ? $image : null;
    }

    private function fit(GdImage $image, int $maxSize): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min($maxSize / $width, $maxSize / $height, 1);

        if ($ratio >= 1) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function encode(GdImage $image, string $extension): ?string
    {
        ob_start();

        $ok = match (strtolower($extension)) {
            'png' => imagepng($image, null, 6),
            'webp' => function_exists('imagewebp') && imagewebp($image, null, 82),
            default => imagejpeg($image, null, 85),
        };

        $data = ob_get_clean();

        return $ok && $data !== false && $data !== '' ? $data : null;
    }
}
