<?php

namespace App\Services\System;

use App\Enums\AccountStatus;
use App\Enums\AdminRole;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\User;
use App\Support\Store;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Production-readiness checks shared by `php artisan store:check-production`
 * and the admin system page. Every message is safe to display: it never
 * includes a password, key, token, DSN or other secret value.
 */
class SystemHealth
{
    /** Pending jobs older than this suggest no queue worker is running. */
    public const STUCK_JOB_MINUTES = 10;

    public function __construct(private readonly SchedulerHeartbeat $heartbeat) {}

    /**
     * @return list<CheckResult>
     */
    public function checks(): array
    {
        $databaseOk = $this->databaseWorks();

        $checks = [
            $this->appKey(),
            $this->environment(),
            $this->debug(),
            $this->https(),
            $this->sessionCookie(),
            $this->logLevel(),
            $databaseOk
                ? CheckResult::ok('database', 'قاعدة البيانات', 'الاتصال يعمل')
                : CheckResult::critical('database', 'قاعدة البيانات', 'تعذر الاتصال بقاعدة البيانات.'),
            $this->cache(),
            $this->storageLink(),
            ...$this->writableDirectories(),
            $this->devDrivers(),
        ];

        if (! $databaseOk) {
            return $checks; // everything below needs the database
        }

        return [
            ...$checks,
            $this->migrations(),
            ...$this->queue(),
            $this->scheduler(),
            $this->demoData(),
            $this->admin(),
            $this->deliveryZone(),
            $this->products(),
            ...$this->storeSettings(),
        ];
    }

    /**
     * The short "ready to launch" list shown on the admin system page.
     *
     * @param  list<CheckResult>|null  $checks  already computed checks (avoids running them twice)
     * @return list<CheckResult>
     */
    public function launchChecklist(?array $checks = null): array
    {
        $wanted = [
            'setting_store_name', 'setting_store_logo', 'setting_store_phone', 'setting_store_address',
            'delivery_zone', 'products', 'admin', 'https', 'debug', 'scheduler', 'queue_worker',
        ];

        $byKey = collect($checks ?? $this->checks())->keyBy('key');

        return collect($wanted)
            ->map(fn (string $key) => $byKey->get($key) ?? CheckResult::critical($key, $key, 'تعذر الفحص (قاعدة البيانات غير متاحة).'))
            ->values()
            ->all();
    }

    /**
     * Non-secret queue facts for the admin page.
     *
     * @return array{connection: string, driver: string, pending: ?int, failed: ?int, oldest_pending_minutes: ?int}
     */
    public function queueStats(): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);

        $pending = null;
        $oldest = null;

        if ($driver === 'database' && $this->tableExists(config("queue.connections.{$connection}.table", 'jobs'))) {
            $table = DB::table(config("queue.connections.{$connection}.table", 'jobs'));
            $pending = (clone $table)->count();
            $oldestAt = (clone $table)->whereNull('reserved_at')->min('available_at');
            $oldest = $oldestAt ? max(0, intdiv(now()->getTimestamp() - (int) $oldestAt, 60)) : null;
        }

        $failed = $this->tableExists('failed_jobs') ? DB::table('failed_jobs')->count() : null;

        return [
            'connection' => $connection,
            'driver' => $driver,
            'pending' => $pending,
            'failed' => $failed,
            'oldest_pending_minutes' => $oldest,
        ];
    }

    private function appKey(): CheckResult
    {
        return filled(config('app.key'))
            ? CheckResult::ok('app_key', 'مفتاح التطبيق APP_KEY', 'موجود')
            : CheckResult::critical('app_key', 'مفتاح التطبيق APP_KEY', 'غير موجود. شغّل php artisan key:generate مرة واحدة.');
    }

    private function environment(): CheckResult
    {
        $env = (string) config('app.env');

        return $env === 'production'
            ? CheckResult::ok('environment', 'بيئة التشغيل', 'production')
            : CheckResult::warning('environment', 'بيئة التشغيل', "البيئة الحالية «{$env}» وليست production.");
    }

    private function debug(): CheckResult
    {
        return config('app.debug')
            ? CheckResult::critical('debug', 'وضع التصحيح APP_DEBUG', 'مفعّل! يجب أن يكون APP_DEBUG=false في الإنتاج (يكشف تفاصيل داخلية).')
            : CheckResult::ok('debug', 'وضع التصحيح APP_DEBUG', 'معطّل');
    }

    private function https(): CheckResult
    {
        return Str::startsWith((string) config('app.url'), 'https://')
            ? CheckResult::ok('https', 'رابط الموقع HTTPS', 'رابط الموقع يستخدم https.')
            : CheckResult::critical('https', 'رابط الموقع HTTPS', 'رابط الموقع (APP_URL) لا يستخدم https.');
    }

    private function sessionCookie(): CheckResult
    {
        return config('session.secure')
            ? CheckResult::ok('session_secure', 'ملف تعريف الجلسة الآمن', 'يُرسل عبر HTTPS فقط')
            : CheckResult::warning('session_secure', 'ملف تعريف الجلسة الآمن', 'SESSION_SECURE_COOKIE غير مفعّل.');
    }

    private function logLevel(): CheckResult
    {
        $level = (string) config('logging.channels.'.config('logging.default').'.level', 'debug');

        return in_array($level, ['debug'], true)
            ? CheckResult::warning('log_level', 'مستوى السجلات', 'LOG_LEVEL=debug يملأ السجلات؛ يُنصح بـ warning أو error في الإنتاج.')
            : CheckResult::ok('log_level', 'مستوى السجلات', $level);
    }

    private function databaseWorks(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cache(): CheckResult
    {
        try {
            $key = 'system-check:'.Str::random(8);
            Cache::put($key, 'ok', 60);
            $works = Cache::get($key) === 'ok';
            Cache::forget($key);
        } catch (Throwable) {
            $works = false;
        }

        return $works
            ? CheckResult::ok('cache', 'الكاش', 'يعمل ('.config('cache.default').')')
            : CheckResult::critical('cache', 'الكاش', 'الكتابة/القراءة من الكاش فشلت.');
    }

    private function storageLink(): CheckResult
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        $ok = is_dir($link) && realpath($link) === realpath($target);

        return $ok
            ? CheckResult::ok('storage_link', 'رابط الصور public/storage', 'موجود')
            : CheckResult::critical('storage_link', 'رابط الصور public/storage', 'غير موجود؛ الصور لن تظهر. شغّل php artisan storage:link');
    }

    /**
     * @return list<CheckResult>
     */
    private function writableDirectories(): array
    {
        $paths = [
            'storage/app' => storage_path('app'),
            'storage/app/public' => storage_path('app/public'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $notWritable = array_keys(array_filter($paths, fn (string $path) => ! is_dir($path) || ! is_writable($path)));

        return [$notWritable === []
            ? CheckResult::ok('writable', 'صلاحيات الكتابة', 'مجلدات storage و bootstrap/cache قابلة للكتابة')
            : CheckResult::critical('writable', 'صلاحيات الكتابة', 'غير قابلة للكتابة: '.implode('، ', $notWritable))];
    }

    /** Development-only drivers must never be used in production. */
    private function devDrivers(): CheckResult
    {
        $logDrivers = array_filter([
            config('otp.driver') === 'log' ? 'OTP_DRIVER=log' : null,
            ...array_map(
                fn (string $channel) => config("messaging.channels.{$channel}.driver") === 'log' ? strtoupper($channel).'=log' : null,
                array_keys((array) config('messaging.channels', [])),
            ),
            config('mail.default') === 'log' ? 'MAIL_MAILER=log' : null,
        ]);

        if ($logDrivers === []) {
            return CheckResult::ok('dev_drivers', 'مزودات التطوير', 'لا توجد مزودات تجريبية مفعّلة');
        }

        $message = 'مزودات تكتب في السجل فقط (للتطوير): '.implode('، ', $logDrivers);

        return in_array('MAIL_MAILER=log', $logDrivers, true) && count($logDrivers) === 1
            ? CheckResult::warning('dev_drivers', 'مزودات التطوير', $message)
            : CheckResult::critical('dev_drivers', 'مزودات التطوير', $message);
    }

    private function migrations(): CheckResult
    {
        try {
            $migrator = app('migrator');

            if (! $migrator->repositoryExists()) {
                return CheckResult::critical('migrations', 'ترحيلات قاعدة البيانات', 'لم تُشغّل الترحيلات بعد (php artisan migrate --force).');
            }

            $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);
            $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
        } catch (Throwable) {
            return CheckResult::warning('migrations', 'ترحيلات قاعدة البيانات', 'تعذر فحص الترحيلات.');
        }

        return $pending === []
            ? CheckResult::ok('migrations', 'ترحيلات قاعدة البيانات', 'كلها مطبّقة')
            : CheckResult::critical('migrations', 'ترحيلات قاعدة البيانات', count($pending).' ترحيلات غير مطبّقة.');
    }

    /**
     * @return list<CheckResult>
     */
    private function queue(): array
    {
        $stats = $this->queueStats();
        $results = [];

        if ($stats['driver'] === 'sync') {
            $results[] = CheckResult::warning('queue_config', 'إعداد الطوابير', 'QUEUE_CONNECTION=sync: الإشعارات تُنفّذ داخل الطلب نفسه. يُنصح بـ database مع عامل طوابير.');
        } elseif ($stats['driver'] === 'database' && $stats['pending'] === null) {
            $results[] = CheckResult::critical('queue_config', 'إعداد الطوابير', 'جدول jobs غير موجود.');
        } else {
            $results[] = CheckResult::ok('queue_config', 'إعداد الطوابير', "الاتصال {$stats['connection']}");
        }

        $results[] = $stats['failed'] === null
            ? CheckResult::critical('failed_jobs', 'المهام الفاشلة', 'جدول failed_jobs غير موجود.')
            : ($stats['failed'] > 0
                ? CheckResult::warning('failed_jobs', 'المهام الفاشلة', "{$stats['failed']} مهمة فاشلة تحتاج مراجعة.")
                : CheckResult::ok('failed_jobs', 'المهام الفاشلة', 'لا توجد'));

        $stuck = $stats['oldest_pending_minutes'] !== null && $stats['oldest_pending_minutes'] >= self::STUCK_JOB_MINUTES;

        $results[] = match (true) {
            $stats['driver'] === 'sync' => CheckResult::warning('queue_worker', 'عامل الطوابير', 'لا يوجد عامل (sync).'),
            $stuck => CheckResult::critical('queue_worker', 'عامل الطوابير', "مهام تنتظر منذ {$stats['oldest_pending_minutes']} دقيقة؛ يبدو أن queue:work متوقف."),
            default => CheckResult::ok('queue_worker', 'عامل الطوابير', 'لا توجد مهام عالقة'),
        };

        return $results;
    }

    private function scheduler(): CheckResult
    {
        $last = $this->heartbeat->lastRunAt();

        return match (true) {
            $last === null => CheckResult::critical('scheduler', 'المُجدول (cron)', 'لم يعمل أبدًا. أضف مهمة cron تشغّل schedule:run كل دقيقة.'),
            ! $this->heartbeat->isRunning() => CheckResult::critical('scheduler', 'المُجدول (cron)', 'متوقف؛ آخر تشغيل '.$last->diffForHumans().'.'),
            default => CheckResult::ok('scheduler', 'المُجدول (cron)', 'يعمل؛ آخر تشغيل '.$last->diffForHumans().'.'),
        };
    }

    private function demoData(): CheckResult
    {
        $found = array_filter([
            Product::withTrashed()->where('sku', DemoDataSeeder::PRODUCT_SKU)->where('status', '!=', ProductStatus::Hidden)->exists() ? 'منتج تجريبي' : null,
            Category::where('slug', DemoDataSeeder::CATEGORY_SLUG)->exists() ? 'قسم تجريبي' : null,
            User::where('phone', DemoDataSeeder::CUSTOMER_PHONE)->where('status', AccountStatus::Active)->exists() ? 'زبون تجريبي' : null,
        ]);

        return $found === []
            ? CheckResult::ok('demo_data', 'البيانات التجريبية', 'لا توجد')
            : CheckResult::critical('demo_data', 'البيانات التجريبية', 'موجودة ('.implode('، ', $found).'). شغّل php artisan store:purge-demo');
    }

    private function admin(): CheckResult
    {
        $exists = User::query()->admins()->active()
            ->whereHas('admin', fn ($q) => $q->where('role', AdminRole::SuperAdmin))
            ->exists();

        return $exists
            ? CheckResult::ok('admin', 'حساب مدير عام', 'موجود')
            : CheckResult::critical('admin', 'حساب مدير عام', 'لا يوجد مدير عام فعّال. شغّل php artisan store:create-admin');
    }

    private function deliveryZone(): CheckResult
    {
        $count = DeliveryZone::query()->active()->count();

        return $count > 0
            ? CheckResult::ok('delivery_zone', 'مناطق التوصيل', "{$count} منطقة فعّالة")
            : CheckResult::critical('delivery_zone', 'مناطق التوصيل', 'لا توجد منطقة توصيل فعّالة؛ لن يتمكن الزبائن من الطلب.');
    }

    private function products(): CheckResult
    {
        $count = Product::query()->storefront()->count();

        return $count > 0
            ? CheckResult::ok('products', 'المنتجات', "{$count} منتج ظاهر في المتجر")
            : CheckResult::critical('products', 'المنتجات', 'لا توجد منتجات ظاهرة في المتجر.');
    }

    /**
     * @return list<CheckResult>
     */
    private function storeSettings(): array
    {
        $required = [
            'store_name' => ['اسم المتجر', Store::name()],
            'store_phone' => ['هاتف المتجر', Store::phone()],
            'store_address' => ['عنوان المتجر', Store::address()],
        ];
        $recommended = [
            'store_logo' => ['شعار المتجر', Store::logoUrl()],
            'store_whatsapp' => ['واتساب المتجر', Store::whatsapp()],
        ];

        $results = [];

        foreach ($required as $key => [$label, $value]) {
            $results[] = filled($value)
                ? CheckResult::ok("setting_{$key}", $label, 'مُعبّأ')
                : CheckResult::critical("setting_{$key}", $label, 'غير مُعبّأ (الإعدادات ← إعدادات المتجر).');
        }

        foreach ($recommended as $key => [$label, $value]) {
            $results[] = filled($value)
                ? CheckResult::ok("setting_{$key}", $label, 'مُعبّأ')
                : CheckResult::warning("setting_{$key}", $label, 'غير مُعبّأ (مستحسن قبل الإطلاق).');
        }

        return $results;
    }

    private function tableExists(string $table): bool
    {
        return rescue(fn () => Schema::hasTable($table), false, report: false);
    }
}
