<?php

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

/**
 * Default store settings. Existing values are never overwritten, so this
 * seeder is safe to re-run after the admin has edited the settings.
 */
class StoreSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'store_name', 'value' => 'بيت المونة', 'type' => SettingType::String, 'group' => 'general', 'label' => 'اسم المتجر'],
            ['key' => 'store_logo', 'value' => null, 'type' => SettingType::Image, 'group' => 'general', 'label' => 'الشعار'],
            ['key' => 'store_phone', 'value' => null, 'type' => SettingType::String, 'group' => 'contact', 'label' => 'الهاتف'],
            ['key' => 'store_whatsapp', 'value' => null, 'type' => SettingType::String, 'group' => 'contact', 'label' => 'واتساب'],
            ['key' => 'store_address', 'value' => 'الخليل – فلسطين', 'type' => SettingType::Text, 'group' => 'contact', 'label' => 'العنوان'],
            ['key' => 'currency_code', 'value' => 'ILS', 'type' => SettingType::String, 'group' => 'orders', 'label' => 'العملة'],
            ['key' => 'currency_symbol', 'value' => '₪', 'type' => SettingType::String, 'group' => 'orders', 'label' => 'رمز العملة'],
            ['key' => 'min_order_amount', 'value' => '0', 'type' => SettingType::Decimal, 'group' => 'orders', 'label' => 'الحد الأدنى للطلب'],
            ['key' => 'working_hours', 'value' => null, 'type' => SettingType::Text, 'group' => 'contact', 'label' => 'ساعات العمل'],
        ];

        foreach ($settings as $setting) {
            StoreSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
