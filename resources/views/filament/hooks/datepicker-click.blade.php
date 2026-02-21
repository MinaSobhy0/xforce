<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make date/time picker inputs open calendar on click anywhere in the input wrapper
    function initDatePickerClicks() {
        // Target the input wrapper (fi-input-wrp) that contains date pickers
        document.querySelectorAll('.fi-fo-date-time-picker').forEach(function(picker) {
            // Check if already initialized
            if (picker.hasAttribute('data-click-init')) return;
            picker.setAttribute('data-click-init', 'true');

            // Get the input wrapper (the clickable area)
            const inputWrapper = picker.closest('.fi-input-wrp');
            if (!inputWrapper) return;

            // Get Alpine component data
            const alpineData = Alpine.$data(picker);
            if (!alpineData || typeof alpineData.togglePanelVisibility !== 'function') return;

            // Make cursor indicate clickability
            inputWrapper.style.cursor = 'pointer';

            // On wrapper click, toggle the panel
            inputWrapper.addEventListener('click', function(e) {
                // Don't trigger if clicking on the panel itself
                if (e.target.closest('.fi-fo-date-time-picker-panel')) return;
                // Don't trigger if clicking on the affix/suffix action buttons (they have their own handlers)
                if (e.target.closest('.fi-input-wrp-suffix-action')) return;

                alpineData.togglePanelVisibility();
            });
        });
    }

    // Wait for Alpine to be ready
    document.addEventListener('alpine:init', function() {
        setTimeout(initDatePickerClicks, 100);
    });

    // Initialize after a delay to ensure Alpine has loaded
    setTimeout(initDatePickerClicks, 500);

    // Re-initialize when Livewire updates the DOM
    if (typeof Livewire !== 'undefined') {
        // Livewire 2
        if (Livewire.hook) {
            Livewire.hook('message.processed', function() {
                setTimeout(initDatePickerClicks, 100);
            });
        }
        // Livewire 3
        document.addEventListener('livewire:navigated', function() {
            setTimeout(initDatePickerClicks, 100);
        });
    }

    // Observer for dynamically added elements (modals, etc.)
    const observer = new MutationObserver(function(mutations) {
        let shouldInit = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && (
                        node.classList?.contains('fi-fo-date-time-picker') ||
                        node.querySelector?.('.fi-fo-date-time-picker')
                    )) {
                        shouldInit = true;
                    }
                });
            }
        });
        if (shouldInit) {
            setTimeout(initDatePickerClicks, 100);
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
</script>
