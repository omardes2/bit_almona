<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('carts:prune-guests {--days=30}')]
#[Description('حذف سلال الزوار المهجورة')]
class PruneGuestCarts extends Command
{
    public function handle(): int
    {
        $count = Cart::query()
            ->whereNull('user_id')
            ->where('updated_at', '<', now()->subDays((int) $this->option('days')))
            ->delete();

        $this->info("تم حذف {$count} سلة مهجورة.");

        return self::SUCCESS;
    }
}
