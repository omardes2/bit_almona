<?php

namespace App\Livewire\Admin\Settings;

use App\Services\System\IntegrationStatus;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Status only. There is deliberately no form: provider credentials are set
 * in .env on the server and never typed into (or shown by) the admin panel.
 */
#[Layout('layouts.admin')]
#[Title('التكاملات')]
class Integrations extends Component
{
    public function boot(): void
    {
        Gate::authorize('manage-system');
    }

    public function render(IntegrationStatus $status)
    {
        return view('livewire.admin.settings.integrations', [
            'integrations' => $status->all(),
        ]);
    }
}
