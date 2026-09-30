<?php

namespace App\Console\Commands;

use App\Services\System\SchedulerHeartbeat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:heartbeat')]
#[Description('تسجيل آخر تشغيل للمُجدول (يُشغّل كل دقيقة عبر schedule:run)')]
class SchedulerHeartbeatCommand extends Command
{
    public function handle(SchedulerHeartbeat $heartbeat): int
    {
        $heartbeat->beat();

        return self::SUCCESS;
    }
}
