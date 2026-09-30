<?php

namespace App\Console\Commands;

use App\Services\System\CheckResult;
use App\Services\System\SystemHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Pre-launch / post-deploy check. Exits with 1 when any critical check fails
 * so deploy pipelines can stop. Output never contains secret values.
 */
#[Signature('store:check-production {--json : Machine-readable output}')]
#[Description('فحص جاهزية بيئة الإنتاج (يخرج برمز 1 عند وجود مشكلة حرجة)')]
class CheckProductionCommand extends Command
{
    public function handle(SystemHealth $health): int
    {
        $checks = $health->checks();
        $critical = array_filter($checks, fn (CheckResult $c) => $c->isCritical());
        $warnings = array_filter($checks, fn (CheckResult $c) => $c->status === CheckResult::WARNING);

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $critical === [],
                'checks' => array_map(fn (CheckResult $c) => ['key' => $c->key, 'status' => $c->status, 'message' => $c->message], $checks),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $critical === [] ? self::SUCCESS : self::FAILURE;
        }

        $icons = [CheckResult::OK => '<fg=green>✔</>', CheckResult::WARNING => '<fg=yellow>!</>', CheckResult::CRITICAL => '<fg=red>✘</>'];

        $this->table(['', 'الفحص', 'النتيجة'], array_map(
            fn (CheckResult $c) => [$icons[$c->status], $c->label, $c->message],
            $checks,
        ));

        if ($critical !== []) {
            $this->error(count($critical).' مشكلة حرجة و'.count($warnings).' تحذير. لا تُطلق المتجر قبل إصلاح المشاكل الحرجة.');

            return self::FAILURE;
        }

        $warnings === []
            ? $this->info('كل الفحوصات سليمة.')
            : $this->warn('لا مشاكل حرجة، مع '.count($warnings).' تحذير.');

        return self::SUCCESS;
    }
}
