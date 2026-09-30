# بيت المونة — التراجع عن إصدار (Rollback)

> اقرأ هذا الملف **قبل** أي تراجع. التراجع عن الكود سهل؛ التراجع عن قاعدة البيانات خطير وقد يُفقد طلبات حقيقية.

## 0. قبل كل نشر (Deploy)
1. سجّل رقم الإصدار الحالي (يطبعه `scripts/deploy.sh` في أول سطر، ويظهر في `/admin/system`).
2. خذ نسخة احتياطية لقاعدة البيانات **قبل** تشغيل الترحيلات:
   ```bash
   mysqldump --single-transaction --routines --default-character-set=utf8mb4 -u USER -p DB \
     | gzip > /var/backups/bait-almona/pre-deploy_$(date +%F_%H%M).sql.gz
   ```
   احفظ النسخ **خارج** مجلد `public/` دائمًا.
3. اقرأ ملفات الترحيل الجديدة (`database/migrations`) لتعرف هل تحذف أعمدة أو جداول.

## 1. تراجع الكود فقط (الحالة الأكثر شيوعًا — آمن)
استخدمه عندما لا يحتوي الإصدار الجديد على ترحيلات، أو ترحيلاته **تضيف** فقط (أعمدة/جداول جديدة):
```bash
cd /var/www/bait-almona
./scripts/deploy.sh <رقم-الإصدار-السابق>     # مثال: ./scripts/deploy.sh 41f49b4
```
- الأعمدة الجديدة التي أضافها الإصدار الملغى تبقى في قاعدة البيانات ولا تضر الكود القديم.
- `deploy.sh` يعيد البناء والكاش ويعيد تشغيل عمّال الطوابير تلقائيًا.

## 2. التراجع عن ترحيل (Migration rollback) — بحذر شديد
⚠️ **لا يوجد تراجع تلقائي للترحيلات في هذا المشروع، عن قصد.**

`php artisan migrate:rollback` ينفّذ دوال `down()` التي قد **تحذف أعمدة وبياناتها نهائيًا** (مثل طلبات أُنشئت بعد النشر).
استخدمه فقط إذا:
- الترحيل الجديد نفسه يكسر الموقع، **و**
- أخذت نسخة احتياطية قبل النشر، **و**
- راجعت دالة `down()` وتأكدت أنها لا تحذف بيانات تحتاجها.

```bash
php artisan down
php artisan migrate:rollback --step=1 --force     # خطوة واحدة فقط، ثم افحص
./scripts/deploy.sh <رقم-الإصدار-السابق>
```

## 3. الاسترجاع الكامل من نسخة احتياطية (آخر حل)
يعيد قاعدة البيانات لوقت النسخة: **كل الطلبات والتسجيلات بعدها تضيع**. صدّر طلبات الفترة أولًا من `/admin/orders` (CSV) إن أمكن.
```bash
php artisan down
gunzip < /var/backups/bait-almona/pre-deploy_YYYY-MM-DD_HHMM.sql.gz | mysql -u USER -p DB
./scripts/deploy.sh <رقم-الإصدار-المطابق-للنسخة>
```

## 4. اختبار الاسترجاع (إلزامي قبل الإطلاق ثم شهريًا)
لا تعتبر النسخ الاحتياطية صالحة قبل تجربة استرجاعها فعليًا — **على قاعدة منفصلة، وليس قاعدة الإنتاج أو التطوير الحالية**:
```bash
mysql -u root -p -e "CREATE DATABASE bait_almona_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip < backup.sql.gz | mysql -u root -p bait_almona_restore_test
mysql -u root -p bait_almona_restore_test -e "SELECT COUNT(*) FROM orders; SELECT COUNT(*) FROM products; SELECT MAX(created_at) FROM orders;"
mysql -u root -p -e "DROP DATABASE bait_almona_restore_test"
```
سجّل تاريخ الاختبار والأرقام. `php artisan store:backup-status` يفحص عمر وحجم آخر نسخة (قراءة فقط).

## 5. بعد أي تراجع
1. `php artisan store:check-production` يجب أن ينتهي برمز 0.
2. افتح `/admin/system` وتأكد من رقم الإصدار والمُجدول والطوابير.
3. جرّب طلبًا تجريبيًا كاملًا ثم ألغه.
