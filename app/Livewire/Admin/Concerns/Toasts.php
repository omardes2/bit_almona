<?php

namespace App\Livewire\Admin\Concerns;

trait Toasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    /**
     * Toast that survives a redirect (shown by the admin layout).
     */
    protected function flashToast(string $message, string $type = 'success'): void
    {
        session()->flash('toast', ['message' => $message, 'type' => $type]);
    }
}
