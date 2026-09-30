<?php

namespace App\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded images under organised folders (categories/, products/{id}/,
 * offers/, banners/) with random, unique file names. Every image gets a WebP
 * thumbnail at "{dir}/thumbs/{name}.webp" used by list pages.
 */
class ImageStorage
{
    public function __construct(private readonly ImageProcessor $processor) {}

    public static function disk(): Filesystem
    {
        return Storage::disk(config('store.images.disk'));
    }

    public function store(UploadedFile $file, string $directory): string
    {
        // Never trust the client file name: extension comes from the detected MIME type.
        $extension = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $contents = (string) file_get_contents($file->getRealPath());

        return $this->storeContents($contents, $extension, $directory);
    }

    /**
     * Copy an existing image (and its thumbnail) to a new directory, under a new name.
     */
    public function copy(string $path, string $directory): ?string
    {
        $disk = self::disk();

        if (! $disk->exists($path)) {
            return null;
        }

        $newPath = trim($directory, '/').'/'.$this->newName(pathinfo($path, PATHINFO_EXTENSION));
        $disk->copy($path, $newPath);

        if ($disk->exists(self::thumbnailPath($path))) {
            $disk->copy(self::thumbnailPath($path), self::thumbnailPath($newPath));
        }

        return $newPath;
    }

    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        self::disk()->delete([$path, self::thumbnailPath($path)]);
    }

    public function deleteDirectory(string $directory): void
    {
        self::disk()->deleteDirectory($directory);
    }

    public static function url(?string $path): ?string
    {
        return blank($path) ? null : self::disk()->url($path);
    }

    public static function thumbnailUrl(?string $path): ?string
    {
        return blank($path) ? null : self::disk()->url(self::thumbnailPath($path));
    }

    public static function thumbnailPath(string $path): string
    {
        return trim(dirname($path), './').'/thumbs/'.pathinfo($path, PATHINFO_FILENAME).'.webp';
    }

    private function storeContents(string $contents, string $extension, string $directory): string
    {
        $config = config('store.images');
        $path = trim($directory, '/').'/'.$this->newName($extension);

        $optimized = $this->processor->optimize($contents, $extension, $config['original_max_dimension']);
        $thumbnail = $this->processor->thumbnail($optimized ?? $contents, $config['thumbnail_size']);

        $disk = self::disk();
        $disk->put($path, $optimized ?? $contents);
        // If processing is impossible the original doubles as the thumbnail, so the path is always valid.
        $disk->put(self::thumbnailPath($path), $thumbnail ?? $optimized ?? $contents);

        return $path;
    }

    private function newName(string $extension): string
    {
        return Str::lower((string) Str::ulid()).'.'.strtolower($extension);
    }
}
