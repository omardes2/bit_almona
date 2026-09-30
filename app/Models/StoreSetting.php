<?php

namespace App\Models;

use App\Enums\SettingType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'type', 'group', 'label'])]
class StoreSetting extends Model
{
    use Auditable;

    public const CACHE_KEY = 'store_settings.all';

    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::memo()->forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::memo()->forget(self::CACHE_KEY));
    }

    /**
     * All settings as a [key => typed value] array, cached. Memoized for the
     * current request/job too, so the many Store::*() calls on one page cost
     * a single cache read (one DB query with CACHE_STORE=database).
     *
     * @return array<string, mixed>
     */
    public static function allValues(): array
    {
        return Cache::memo()->rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get()
            ->mapWithKeys(fn (self $setting) => [$setting->key => $setting->type->cast($setting->value)])
            ->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value, ?SettingType $type = null): self
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->type = $type ?? $setting->type ?? SettingType::String;
        $setting->value = $setting->type->serialize($value);
        $setting->save();

        return $setting;
    }
}
