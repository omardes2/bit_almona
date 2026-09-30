// Alpine.js is bundled with Livewire 4; register components when it boots.
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // Header / bottom-nav cart badge. Set from the server on each page load,
    // updated by the `cart-updated` Livewire event.
    Alpine.store('cart', { count: 0 });

    Alpine.data('toasts', (initial = null) => ({
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

    // Minimal banner carousel: native scroll-snap + optional autoplay, no library.
    Alpine.data('carousel', (count = 1, interval = 5000) => ({
        index: 0,
        count,
        timer: null,

        init() {
            if (this.count > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.timer = setInterval(() => this.go(this.index + 1), interval);
            }
        },

        destroy() {
            clearInterval(this.timer);
        },

        go(i) {
            this.index = (i + this.count) % this.count;
            const track = this.$refs.track;
            // Slides are full width. In RTL, scrollLeft runs from 0 to negative values.
            const direction = getComputedStyle(track).direction === 'rtl' ? -1 : 1;
            track.scrollTo({ left: direction * this.index * track.clientWidth, behavior: 'smooth' });
        },

        onScroll() {
            const track = this.$refs.track;
            const width = track.clientWidth || 1;
            this.index = Math.min(this.count - 1, Math.round(Math.abs(track.scrollLeft) / width));
        },

        pause() {
            clearInterval(this.timer);
        },
    }));

    // Quantity picker. Works in integer thousandths so 0.25 kg steps are exact.
    // The server re-validates everything; this only prevents obviously invalid input.
    Alpine.data('quantityPicker', (min, step, max) => ({
        min,
        step,
        max,
        value: min,

        get display() {
            return String(this.value / 1000);
        },

        get canIncrease() {
            return this.value + this.step <= this.max;
        },

        get canDecrease() {
            return this.value - this.step >= this.min;
        },

        increase() {
            if (this.canIncrease) this.value += this.step;
        },

        decrease() {
            if (this.canDecrease) this.value -= this.step;
        },
    }));
});
