# بيت المونة — بيئة Staging (التجربة قبل الإنتاج)

Staging نسخة مطابقة للإنتاج لتجربة الإصدارات قبل نشرها. **ليست للزبائن الحقيقيين.**

## القواعد
- **قاعدة بيانات منفصلة** ومستخدم MySQL منفصل (مثل `bait_almona_staging`). لا تتصل أبدًا بقاعدة الإنتاج.
- **ملف `.env` منفصل** بمفتاح `APP_KEY` مختلف عن الإنتاج.
- دومين فرعي محمي، مثل `staging.example.com`، ويفضّل حمايته بكلمة مرور على مستوى الخادم (HTTP Basic Auth).
- **لا زبائن حقيقيين، لا واتساب/SMS حقيقي، لا دفع حقيقي**: اترك `WHATSAPP_PROVIDER` و`SMS_PROVIDER` و`OTP_DRIVER` فارغة.
- لا تنسخ بيانات الإنتاج (أرقام الزبائن وعناوينهم) إلى Staging. إذا احتجت بيانات، استخدم البيانات التجريبية.

## قيم `.env` الأساسية
```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.example.com
DB_DATABASE=bait_almona_staging
LOG_LEVEL=debug
SEED_DEMO_DATA=true          # مسموح في staging فقط
WHATSAPP_PROVIDER=
SMS_PROVIDER=
OTP_DRIVER=
PAYMENT_COD_ENABLED=true
```

## ما يفعله التطبيق تلقائيًا في Staging
- `robots.txt` يعيد `Disallow: /` لكل محركات البحث.
- كل الاستجابات تحمل الترويسة `X-Robots-Tag: noindex, nofollow`.
- `store:check-production` يعرض تحذير «البيئة ليست production» — هذا متوقع.

## النشر على Staging
نفس السكربت المستخدم في الإنتاج:
```bash
./scripts/deploy.sh <branch-or-commit>
php artisan db:seed --force           # يضيف البيانات التجريبية لأن APP_ENV ليست production
```

## قبل ترقية إصدار من Staging إلى الإنتاج
1. كل الاختبارات ناجحة (`php artisan test`).
2. جرّب على الجوال: تصفح ← سلة ← تسجيل ← طلب ← تغيير الحالات من اللوحة ← التقارير.
3. `php artisan store:check-production` بلا مشاكل حرجة سوى البيئة/البيانات التجريبية.
