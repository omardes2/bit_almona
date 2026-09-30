<?php

namespace App\Livewire\Admin\Concerns;

use Illuminate\Support\Facades\Gate;

/**
 * Runs on every request of the component (initial render and every Livewire
 * update), so catalog actions cannot be called by admins without permission.
 */
trait AuthorizesCatalog
{
    public function bootAuthorizesCatalog(): void
    {
        Gate::authorize('manage-catalog');
    }
}
