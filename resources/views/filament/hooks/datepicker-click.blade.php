<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make date/time picker inputs open calendar on click
    function initDatePickerClicks() {
        // Target all date-time picker input wrappers
        document.querySelectorAll('[x-data*="dateTimePickerFormComponent"]').forEach(function(picker) {
            const input = picker.querySelector('input[type="text"]');
            const button = picker.querySelector('button[x-on\\:click*="togglePanelVisibility"]')
                        || picker.querySelector('[x-on\\:click*="togglePanelVisibility"]')
                        || picker.querySelector('.fi-input-wrp button');

            if (input && button) {
                // Check if we've already initialized this input
                if (input.hasAttribute('data-datepicker-click-init')) return;
                input.setAttribute('data-datepicker-click-init', 'true');

                // Make cursor indicate clickability
                input.style.cursor = 'pointer';

                // On input click, trigger the button click
                input.addEventListener('click', function(e) {
                    // Don't trigger if user is selecting text
                    if (window.getSelection().toString()) return;
                    button.click();
                });
            }
        });
    }

    // Initialize on page load
    initDatePickerClicks();

    // Re-initialize when Livewire updates the DOM
    if (typeof Livewire !== 'undefined') {
        Livewire.hook('message.processed', function() {
            setTimeout(initDatePickerClicks, 100);
        });
        // For Livewire 3
        document.addEventListener('livewire:navigated', initDatePickerClicks);
        document.addEventListener('livewire:load', initDatePickerClicks);
    }

    // Observer for dynamically added elements (modals, etc.)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                setTimeout(initDatePickerClicks, 100);
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
</script>
