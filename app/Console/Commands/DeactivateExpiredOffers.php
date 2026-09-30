<?php

namespace App\Console\Commands;

use App\Models\Offer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('offers:deactivate-expired')]
#[Description('إيقاف العروض التي انتهى تاريخها تلقائيًا')]
class DeactivateExpiredOffers extends Command
{
    public function handle(): int
    {
        $count = Offer::query()->where('is_active', true)->expired()->update(['is_active' => false]);

        $this->info("تم إيقاف {$count} عرض/عروض منتهية.");

        return self::SUCCESS;
    }
}
