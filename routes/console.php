<?php

use Illuminate\Support\Facades\Schedule;

// Offers already stop applying once ends_at passes (see Offer::scopeRunning);
// this keeps the is_active flag in the database in sync as well.
Schedule::command('offers:deactivate-expired')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('carts:prune-guests')->daily();
