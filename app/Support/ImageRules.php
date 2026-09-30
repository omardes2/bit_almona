<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Single place for "is this a safe image upload?" validation.
 * SVG is deliberately not allowed (it can carry scripts).
 */
final class ImageRules
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return array<int, mixed>
     */
    public static function rules(bool $required = false): array
    {
        $config = config('store.images');

        return [
            $required ? 'required' : 'nullable',
            'file',
            'image',
            'extensions:'.implode(',', self::EXTENSIONS),
            'mimes:'.implode(',', self::EXTENSIONS),
            'mimetypes:'.implode(',', self::MIME_TYPES),
            'max:'.$config['max_kilobytes'],
            Rule::dimensions()
                ->minWidth($config['min_dimension'])->minHeight($config['min_dimension'])
                ->maxWidth($config['max_dimension'])->maxHeight($config['max_dimension']),
        ];
    }

    public static function hint(): string
    {
        return 'JPG أو PNG أو WEBP، حتى '.(config('store.images.max_kilobytes') / 1024).' ميغابايت.';
    }
}
