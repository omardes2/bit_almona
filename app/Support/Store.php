<?php

namespace App\Support;

use App\Models\StoreSetting;

final class Store
{
    /**
     * The store name from store_settings, with a safe fallback.
     */
    public static function name(): string
    {
        $name = rescue(fn () => StoreSetting::get('store_name'), null, report: false);

        return filled($name) ? $name : config('store.name');
    }
}
