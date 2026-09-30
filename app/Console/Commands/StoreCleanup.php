<?php

namespace App\Console\Commands;

use App\Models\OtpCode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

#[Signature('store:cleanup')]
#[Description('تنظيف الرموز المنتهية والإشعارات المقروءة القديمة')]
class StoreCleanup extends Command
{
    public function handle(): int
    {
        $otps = OtpCode::query()->where('expires_at', '<', now()->subDay())->delete();
        $notifications = DatabaseNotification::query()->whereNotNull('read_at')->where('read_at', '<', now()->subDays(90))->delete();

        $this->info("OTP: {$otps} · إشعارات: {$notifications}");

        return self::SUCCESS;
    }
}
