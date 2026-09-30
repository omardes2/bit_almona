<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * URL slugs that keep Arabic letters (better for Arabic SEO than a lossy
 * transliteration), e.g. "جبنة الخيرات 24 مثلث" => "جبنة-الخيرات-24-مثلث".
 */
final class Slug
{
    /** Accepts lowercase latin letters, digits, Arabic letters and single dashes. */
    public const PATTERN = '/^[\p{Arabic}a-z0-9]+(?:-[\p{Arabic}a-z0-9]+)*$/u';

    public static function make(string $text): string
    {
        $text = mb_strtolower(trim($text));

        // Remove Arabic diacritics / tatweel but keep the letters as written.
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? '';
        $text = preg_replace('/[^\p{Arabic}\p{Latin}0-9]+/u', '-', $text) ?? '';
        $text = trim($text, '-');

        // Latin letters with accents => plain ASCII.
        $text = preg_replace_callback('/\p{Latin}+/u', fn ($m) => Str::ascii($m[0]), $text) ?? $text;
        $text = preg_replace('/[^\p{Arabic}a-z0-9-]+/u', '', mb_strtolower($text)) ?? '';
        $text = preg_replace('/-+/', '-', trim($text, '-')) ?? '';

        return mb_substr($text, 0, 150);
    }

    /**
     * A slug that is unique in the model's table (soft-deleted rows included,
     * because the database unique index includes them too).
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function unique(string $modelClass, string $text, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = self::make($text) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;

        while (self::exists($modelClass, $slug, $ignoreId, $column)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function exists(string $modelClass, string $slug, ?int $ignoreId, string $column): bool
    {
        $query = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            ? $modelClass::withTrashed()
            : $modelClass::query();

        return $query->where($column, $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }
}
