# بيت المونة — متجر إلكتروني

سوبرماركت بيت المونة، الخليل – فلسطين.

**التقنيات:** Laravel 13 · PHP 8.4 · MySQL · Blade · Livewire 4 · Tailwind CSS 4 · Alpine.js · Vite

## التشغيل محليًا

```bash
composer install
cp .env.example .env          # ثم عبّئ بيانات MySQL و SEED_ADMIN_*
php artisan key:generate
php artisan migrate --seed
php artisan storage:link       # مطلوب لعرض الصور المرفوعة
npm install && npm run build
php artisan serve
```

- المتجر: `/` · `/category/{slug}` · `/product/{slug}` · `/offers` · `/search` · `/cart` · `/sitemap.xml` · `/robots.txt`
- الزبائن: `/login` و `/register` و `/account` · `/account/orders` · `/account/addresses` · `/checkout`
- الإدارة: `/admin/login` و `/admin`
  - المنتجات `/admin/products` · الأقسام `/admin/categories` · العروض `/admin/offers` · البنرات `/admin/banners`
  - الطلبات `/admin/orders` · مناطق التوصيل `/admin/delivery-zones` · الإعدادات `/admin/settings`
  - الصلاحيات: `super_admin` كل شيء · `manager` الكتالوج ومناطق التوصيل والطلبات · `staff` الطلبات فقط.
- المدير الأول يُنشأ من `SEED_ADMIN_PHONE` و `SEED_ADMIN_PASSWORD` في `.env`، أو عبر:
  `php artisan store:create-admin`

## البيانات التجريبية

تُزرع فقط عند `SEED_DEMO_DATA=true` وخارج بيئة الإنتاج (قسم «ألبان وأجبان»، منتج «جبنة الخيرات 24 مثلث»
بسعر 18 وعرض 10 ₪، وزبون تجريبي `0599000001` / `Demo12345` في البيئة المحلية فقط).

للحذف: `php artisan store:purge-demo`

## أوامر مفيدة

| الأمر | الوظيفة |
|---|---|
| `php artisan test` | تشغيل الاختبارات |
| `php artisan offers:deactivate-expired` | إيقاف العروض المنتهية (مجدول كل 5 دقائق) |
| `php artisan carts:prune-guests` | حذف سلال الزوار المهجورة (مجدول يوميًا) |
| `php artisan schedule:work` | تشغيل المجدول محليًا (في الإنتاج: cron لـ `schedule:run`) |
