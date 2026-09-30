// Alpine.js is bundled with Livewire 4; register components when it boots.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('adminToasts', (initial = null) => ({
        toasts: [],
        nextId: 1,

        init() {
            if (initial) this.add(initial);
        },

        add(detail) {
            const toast = {
                id: this.nextId++,
                message: detail.message ?? '',
                type: detail.type ?? 'success',
                visible: true,
            };
            this.toasts.push(toast);
            setTimeout(() => this.remove(toast.id), 4000);
        },

        remove(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    }));
});
