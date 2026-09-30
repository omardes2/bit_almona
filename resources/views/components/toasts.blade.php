{{-- Toasts: Livewire `$this->dispatch('toast', message: ..., type: ...)` or session('toast') after a redirect. --}}
<div x-data="toasts(@js(session('toast')))"
     @toast.window="add($event.detail)"
     class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:bottom-6"
     aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.visible" x-transition.opacity.duration.200ms
             :class="toast.type === 'error' ? 'bg-red-600' : (toast.type === 'warning' ? 'bg-amber-600' : 'bg-gray-900')"
             class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-xl px-4 py-3 text-white shadow-lg">
            <span class="flex-1 text-sm font-medium" x-text="toast.message"></span>
            <button type="button" @click="remove(toast.id)" class="text-white/70 hover:text-white" aria-label="إغلاق">✕</button>
        </div>
    </template>
</div>
