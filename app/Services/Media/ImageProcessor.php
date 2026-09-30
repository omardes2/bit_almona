<?php

namespace App\Services\Media;

/**
 * Image manipulation used by ImageStorage. Swap the binding in
 * AppServiceProvider to change the engine (e.g. Imagick) or to start
 * converting every original to WebP, without touching the rest of the app.
 */
interface ImageProcessor
{
    /**
     * Re-encode an image (optionally downscaled to fit $maxSize) in its own
     * format. Returns the new binary contents, or null if it cannot be processed.
     */
    public function optimize(string $contents, string $extension, int $maxSize): ?string;

    /**
     * Create a WebP thumbnail that fits inside $size x $size.
     */
    public function thumbnail(string $contents, int $size): ?string;
}
