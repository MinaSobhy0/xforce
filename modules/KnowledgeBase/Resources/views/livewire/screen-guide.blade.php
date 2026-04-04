<div>
    @if($isActive && !empty($guide))
        {{-- Overlay --}}
        <div
            x-data="guideOverlay(@js($guide), @js($currentStep))"
            x-show="isActive"
            x-on:guide-step-changed.window="goToStep($event.detail.step)"
            x-on:guide-closed.window="cleanup()"
            class="fixed inset-0 z-[9999]"
            style="display: none;"
        >
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/50 transition-opacity" @click="$wire.skipGuide()"></div>

            {{-- Spotlight (positioned via JS) --}}
            <div
                x-ref="spotlight"
                class="absolute rounded-lg ring-4 ring-primary-500 ring-opacity-50 transition-all duration-300 pointer-events-none"
                style="box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);"
            ></div>

            {{-- Tooltip / Step Card --}}
            <div
                x-ref="tooltip"
                class="absolute z-10 max-w-sm bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 transition-all duration-300"
                x-show="isActive"
                x-transition
            >
                <div class="p-4">
                    {{-- Step Header --}}
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-primary-600 dark:text-primary-400">
                            {{ __('knowledgebase::knowledgebase.step_of', ['current' => $currentStep + 1, 'total' => $totalSteps]) }}
                        </span>
                        <button
                            wire:click="skipGuide"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            title="{{ __('knowledgebase::knowledgebase.skip_guide') }}"
                        >
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    </div>

                    {{-- Step Content --}}
                    @if($stepData)
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">
                            {{ $stepData['title'] ?? '' }}
                        </h4>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ $stepData['content'] ?? '' }}
                        </p>
                    @endif

                    {{-- Progress Bar --}}
                    <div class="mt-4 h-1 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-primary-500 transition-all duration-300"
                            style="width: {{ (($currentStep + 1) / $totalSteps) * 100 }}%"
                        ></div>
                    </div>

                    {{-- Navigation --}}
                    <div class="flex items-center justify-between mt-4">
                        <button
                            wire:click="previousStep"
                            @if($currentStep === 0) disabled @endif
                            class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ __('knowledgebase::knowledgebase.previous') }}
                        </button>

                        <div class="flex gap-1">
                            @for($i = 0; $i < $totalSteps; $i++)
                                <button
                                    wire:click="goToStep({{ $i }})"
                                    class="w-2 h-2 rounded-full transition-colors {{ $i === $currentStep ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600 hover:bg-gray-400' }}"
                                ></button>
                            @endfor
                        </div>

                        @if($currentStep === $totalSteps - 1)
                            <button
                                wire:click="completeGuide"
                                class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                {{ __('knowledgebase::knowledgebase.finish') }}
                            </button>
                        @else
                            <button
                                wire:click="nextStep"
                                class="px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
                            >
                                {{ __('knowledgebase::knowledgebase.next') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    Alpine.data('guideOverlay', (guide, initialStep) => ({
        isActive: true,
        currentStep: initialStep,
        guide: guide,

        init() {
            this.positionElements();
            window.addEventListener('resize', () => this.positionElements());
        },

        goToStep(step) {
            this.currentStep = step;
            this.positionElements();
        },

        positionElements() {
            const step = this.guide.steps[this.currentStep];
            if (!step) return;

            const target = document.querySelector(step.target);
            if (!target) {
                console.warn('Guide target not found:', step.target);
                return;
            }

            const spotlight = this.$refs.spotlight;
            const tooltip = this.$refs.tooltip;
            const rect = target.getBoundingClientRect();
            const padding = 8;

            // Position spotlight
            spotlight.style.top = `${rect.top - padding}px`;
            spotlight.style.left = `${rect.left - padding}px`;
            spotlight.style.width = `${rect.width + padding * 2}px`;
            spotlight.style.height = `${rect.height + padding * 2}px`;

            // Position tooltip based on placement
            const tooltipRect = tooltip.getBoundingClientRect();
            const placement = step.placement || 'bottom';
            const gap = 12;

            let top, left;

            switch (placement) {
                case 'top':
                case 'top-start':
                case 'top-end':
                    top = rect.top - tooltipRect.height - gap;
                    left = placement === 'top-start' ? rect.left :
                           placement === 'top-end' ? rect.right - tooltipRect.width :
                           rect.left + (rect.width - tooltipRect.width) / 2;
                    break;
                case 'bottom':
                case 'bottom-start':
                case 'bottom-end':
                    top = rect.bottom + gap;
                    left = placement === 'bottom-start' ? rect.left :
                           placement === 'bottom-end' ? rect.right - tooltipRect.width :
                           rect.left + (rect.width - tooltipRect.width) / 2;
                    break;
                case 'left':
                case 'left-start':
                case 'left-end':
                    left = rect.left - tooltipRect.width - gap;
                    top = placement === 'left-start' ? rect.top :
                          placement === 'left-end' ? rect.bottom - tooltipRect.height :
                          rect.top + (rect.height - tooltipRect.height) / 2;
                    break;
                case 'right':
                case 'right-start':
                case 'right-end':
                    left = rect.right + gap;
                    top = placement === 'right-start' ? rect.top :
                          placement === 'right-end' ? rect.bottom - tooltipRect.height :
                          rect.top + (rect.height - tooltipRect.height) / 2;
                    break;
                default:
                    top = rect.bottom + gap;
                    left = rect.left + (rect.width - tooltipRect.width) / 2;
            }

            // Keep tooltip within viewport
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            if (left < 10) left = 10;
            if (left + tooltipRect.width > viewportWidth - 10) left = viewportWidth - tooltipRect.width - 10;
            if (top < 10) top = 10;
            if (top + tooltipRect.height > viewportHeight - 10) top = viewportHeight - tooltipRect.height - 10;

            tooltip.style.top = `${top}px`;
            tooltip.style.left = `${left}px`;

            // Scroll target into view if needed
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        cleanup() {
            this.isActive = false;
            window.removeEventListener('resize', () => this.positionElements());
        }
    }));
</script>
@endscript
