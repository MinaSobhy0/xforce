<button
    type="button"
    onclick="
        try { sessionStorage.setItem('xlinicScrollY', String(window.scrollY)); } catch(e) {}
        this.classList.add('fi-loading');
        setTimeout(function () { this && this.classList && this.classList.remove('fi-loading'); }.bind(this), 1200);

        // Prefer Livewire soft-navigation when available (nicer UX,
        // no full page paint). Fall back to plain location.reload().
        // sessionStorage-based scroll restore below covers both paths.
        if (window.Livewire && typeof window.Livewire.navigate === 'function') {
            try {
                window.Livewire.navigate(window.location.href);
                return;
            } catch (e) { /* fall through to reload */ }
        }
        window.location.reload();
    "
    title="Reload data in place"
    aria-label="Reload"
    class="fi-icon-btn relative flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:bg-gray-50 hover:text-gray-500 focus-visible:bg-gray-50 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-400 dark:focus-visible:bg-white/5"
>
    <x-heroicon-o-arrow-path class="h-5 w-5 [.fi-loading_&]:animate-spin" />
</button>

<script>
    (function () {
        function restoreScroll() {
            try {
                var y = sessionStorage.getItem('xlinicScrollY');
                if (y === null) return;
                sessionStorage.removeItem('xlinicScrollY');
                var target = parseInt(y, 10) || 0;
                if (target <= 0) return;
                // Multi-attempt restore. Filament renders widgets and
                // async component data over several ticks — one
                // scrollTo call fires too early and gets clobbered
                // by later layout shifts, so we retry.
                var attempts = 0;
                var tick = function () {
                    window.scrollTo(0, target);
                    attempts++;
                    if (attempts < 4) setTimeout(tick, 100 + attempts * 100);
                };
                window.requestAnimationFrame(tick);
            } catch (e) {}
        }
        // Fire on initial page load AND after a Livewire soft-navigate.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', restoreScroll);
        } else {
            restoreScroll();
        }
        document.addEventListener('livewire:navigated', restoreScroll);
    })();
</script>
