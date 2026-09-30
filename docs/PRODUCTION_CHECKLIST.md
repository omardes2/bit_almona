# بيت المونة — قائمة التشغيل على الخادم (Production Checklist)

> اتبع الخطوات بالترتيب. لا تضع أي سر (كلمات مرور، مفاتيح) داخل Git.

## 1. المتطلبات
- PHP 8.4 مع الإضافات: `pdo_mysql`, `mbstring`, `intl`, `gd` (مع WebP), `fileinfo`, `bcmath` (اختياري).
- MySQL 8 (أو MariaDB 10.11+) بترميز `utf8mb4`.
- Node 20+ (للبناء فقط)، Composer 2.
- خادم ويب (Nginx أو Apache) يوجّه الجذر إلى مجلد `public/`.

## 2. ملف البيئة `.env`
```bash
cp .env.example .env
php artisan key:generate
```
عدّل القيم:
| المتغير | القيمة في الإنتاج |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (مهم جدًا: يمنع عرض تفاصيل الأخطاء) |
| `APP_URL` | `https://الدومين` (تُبنى منه روابط الصور والـ sitemap) |
| `DB_*` | بيانات قاعدة البيانات، بمستخدم خاص بالمتجر فقط |
| `LOG_LEVEL` | `warning` |
| `SESSION_SECURE_COOKIE` | اتركه فارغًا (يصبح `true` تلقائيًا في الإنتاج) |
| `SEED_ADMIN_*`, `SEED_DEMO_DATA` | فارغة / `false` |
| `WHATSAPP_PROVIDER`, `SMS_PROVIDER`, `OTP_DRIVER` | فارغة حتى يتم ربط مزود حقيقي (قيمة `log` للتطوير فقط ويرفضها الفحص) |
| `REQUIRE_PHONE_VERIFICATION` | `false` (لا يُطبّق إلا مع مزود OTP حقيقي) |
| `SESSION_DRIVER` | `database` (مطلوب لإلغاء الجلسات عند استعادة كلمة المرور أو تغيير الجوال) |
| `QUEUE_CONNECTION` | `database` مع عامل طوابير |
| `APP_VERSION` | اختياري؛ `deploy.sh` يكتب رقم الـ commit في ملف `VERSION` |
| `BACKUP_PATH` | مجلد النسخ الاحتياطية خارج `public/` |

- اجعل صلاحيات الملف: `chmod 640 .env` ولا تنسخه إلى Git أبدًا.

## 3. التثبيت والبناء
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=StoreSettingsSeeder --force
php artisan storage:link
php artisan optimize          # config + routes + views + events cache
```
بعد أي تحديث للكود استخدم السكربت: `./scripts/deploy.sh` (وضع الصيانة، composer، الترحيلات `--force`، البناء، الكاش، إعادة تشغيل الطوابير، ثم فحص الجاهزية). للتراجع راجع `docs/ROLLBACK.md`، ولبيئة التجربة `docs/STAGING.md`.

**فحص الجاهزية:** `php artisan store:check-production` — يخرج برمز 1 عند أي مشكلة حرجة (APP_DEBUG، HTTPS، قاعدة البيانات، الكاش، رابط الصور، الصلاحيات، الترحيلات، الطوابير، المُجدول، البيانات التجريبية، المدير العام، منطقة التوصيل، إعدادات المتجر). نفس النتائج في `/admin/system` للمدير العام.

## 4. صلاحيات الملفات
```bash
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```
باقي ملفات المشروع للقراءة فقط لمستخدم الويب.

## 5. العمليات الخلفية
**Scheduler** (cron واحد كل دقيقة):
```cron
* * * * * cd /var/www/bait-almona && php artisan schedule:run >> /dev/null 2>&1
```
يشغّل: إيقاف العروض المنتهية (كل 5 دقائق)، حذف سلال الزوار المهجورة (يوميًا)، تنظيف الرموز والإشعارات القديمة (يوميًا 03:30).

المُجدول يسجّل نبضة كل دقيقة (`system:heartbeat`)؛ إذا توقفت أكثر من 5 دقائق تظهر «متوقف» في `/admin/system` ويفشل `store:check-production`.

**Queue worker** (عبر Supervisor أو systemd):
```bash
php artisan queue:work --queue=notifications,default --tries=3 --max-time=3600
```
مثال Supervisor (`/etc/supervisor/conf.d/bait-almona-worker.conf`):
```ini
[program:bait-almona-worker]
command=php /var/www/bait-almona/artisan queue:work database --queue=notifications,default --tries=3 --backoff=30 --max-time=3600 --sleep=3
user=www-data
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/bait-almona/storage/logs/worker.log
```
حاليًا لا توجد رسائل خارجية مفعلة، لكن الـ worker مطلوب لإشعارات الإدارة المؤجلة وبمجرد ربط واتساب/SMS.
المهام الفاشلة: `/admin/system/failed-jobs` (إعادة/حذف) أو `php artisan queue:failed` و`queue:retry all`.

## 6. HTTPS
- شهادة SSL (مثلًا Let's Encrypt) وتحويل كل HTTP إلى HTTPS.
- التطبيق يرسل `Strict-Transport-Security` تلقائيًا على HTTPS.
- إذا كان خلف Proxy/Cloudflare فعّل `trustProxies` في `bootstrap/app.php`.

## 7. التحقق بعد الإطلاق
1. افتح `/up` ← يجب أن تكون الاستجابة 200.
2. أنشئ المدير الأول: `php artisan store:create-admin` ثم ادخل من `/admin/login`.
3. **الإعدادات** (`/admin/settings`): الاسم، الشعار، الهاتف، واتساب، العنوان، الحد الأدنى.
4. **مناطق التوصيل** (`/admin/delivery-zones`): أضف منطقة واحدة على الأقل (بدونها لا يمكن إتمام الطلب).
5. **الكتالوج**: أنشئ الأقسام ثم المنتجات الحقيقية بالصور والمخزون.
6. احذف البيانات التجريبية إن وُجدت: `php artisan store:purge-demo`.
7. جرّب طلبًا كاملًا من الجوال ثم ألغه من اللوحة وتأكد من رجوع المخزون.
8. افتح رابطًا غير موجود وتأكد أن صفحة 404 عربية ولا تعرض تفاصيل تقنية.

## 8. النسخ الاحتياطي (Backups)
**ما يجب نسخه:**
| العنصر | لماذا |
|---|---|
| قاعدة بيانات MySQL | كل الطلبات والعملاء والمنتجات والإعدادات |
| `storage/app/public` | صور المنتجات والأقسام والبنرات والشعار |
| ملف `.env` | يُحفظ **خارج Git** في مكان آمن ومشفّر (يحتوي `APP_KEY` اللازم لفك الجلسات المشفرة) |

**أمر مقترح لقاعدة البيانات:**
```bash
mysqldump --single-transaction --routines --default-character-set=utf8mb4 -u USER -p DB | gzip > backup_$(date +%F).sql.gz
```
**سياسة الاحتفاظ المقترحة:** يوميًا لآخر 7 أيام، أسبوعيًا لآخر 4 أسابيع، شهريًا لآخر 6 أشهر.
احفظ نسخة واحدة على الأقل خارج الخادم (تخزين سحابي أو جهاز آخر)، ولا تضع النسخ داخل `public/` أبدًا.

- [ ] **اختبر Restore فعلي قبل الإطلاق** على قاعدة منفصلة (الخطوات في `docs/ROLLBACK.md` §4)، ثم كرّره شهريًا.
- ضع مسار النسخ في `BACKUP_PATH` وافحصه بـ `php artisan store:backup-status` (عمر وحجم آخر نسخة، قراءة فقط).

## 9. قبل الإطلاق مباشرة
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`
- [ ] `php artisan store:check-production` ينتهي بـ 0.
- [ ] `/admin/system` ← «جاهزية الإطلاق» كلها خضراء.
- [ ] استبدال نص **سياسة الخصوصية** و**الشروط** بالنص القانوني المعتمد (`resources/views/store/pages/legal.blade.php`).
- [ ] كتابة «نبذة عن المتجر» من الإعدادات (صفحة من نحن).
- [ ] **اختبر Restore فعلي قبل الإطلاق.**
- [ ] حذف البيانات التجريبية: `php artisan store:purge-demo --dry-run` ثم بدون `--dry-run`.

## 10. أمان تشغيلي
- لا تفعّل `APP_DEBUG=true` على الخادم أبدًا.
- حدّث الحزم دوريًا: `composer audit` و `npm audit`.
- راجع `/admin/audit-logs` دوريًا (متاح للمدير العام فقط).
- المدراء بأقل صلاحية لازمة: `staff` للطلبات فقط، `manager` للكتالوج والتقارير، `super_admin` للإعدادات وسجل العمليات.
