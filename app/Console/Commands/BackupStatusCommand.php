<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Reports on the newest dump written by the server's backup job (BACKUP_PATH).
 * Read-only: it never creates, restores or deletes a backup.
 */
#[Signature('store:backup-status {--max-age=26 : Hours before the newest backup counts as stale}')]
#[Description('فحص آخر نسخة احتياطية لقاعدة البيانات (قراءة فقط)')]
class BackupStatusCommand extends Command
{
    public function handle(): int
    {
        $path = (string) config('store.backup_path');

        if ($path === '') {
            $this->warn('BACKUP_PATH غير مضبوط في .env؛ لا يمكن فحص النسخ الاحتياطية.');

            return self::FAILURE;
        }

        if (! is_dir($path) || ! is_readable($path)) {
            $this->error('مجلد النسخ الاحتياطية غير موجود أو غير قابل للقراءة.');

            return self::FAILURE;
        }

        if (Str::startsWith(realpath($path) ?: $path, realpath(public_path()) ?: public_path())) {
            $this->error('خطر: مجلد النسخ الاحتياطية داخل public/ ويمكن تنزيله من الإنترنت. انقله فورًا.');

            return self::FAILURE;
        }

        $files = collect(glob(rtrim($path, '/').'/*.{sql,sql.gz,gz,zip}', GLOB_BRACE) ?: [])
            ->filter(fn (string $file) => is_file($file))
            ->sortByDesc(fn (string $file) => filemtime($file))
            ->values();

        if ($files->isEmpty()) {
            $this->error('لا توجد أي نسخة احتياطية في المجلد.');

            return self::FAILURE;
        }

        $latest = $files->first();
        $at = Carbon::createFromTimestamp(filemtime($latest), config('app.timezone'));
        $size = filesize($latest);
        $stale = $at->lt(now()->subHours((int) $this->option('max-age')));

        $this->table(['', ''], [
            ['آخر نسخة', basename($latest)],
            ['التاريخ', $at->format('Y-m-d H:i').' ('.$at->diffForHumans().')'],
            ['الحجم', Number::fileSize($size)],
            ['عدد النسخ', $files->count()],
        ]);

        if ($size < 1024) {
            $this->error('آخر نسخة صغيرة جدًا (أقل من 1KB) — غالبًا فشل النسخ.');

            return self::FAILURE;
        }

        if ($stale) {
            $this->error('آخر نسخة أقدم من '.$this->option('max-age').' ساعة. تحقق من مهمة النسخ الاحتياطي.');

            return self::FAILURE;
        }

        $this->info('النسخ الاحتياطي يعمل. تذكّر: جرّب الاسترجاع فعليًا على قاعدة منفصلة (docs/ROLLBACK.md).');

        return self::SUCCESS;
    }
}
