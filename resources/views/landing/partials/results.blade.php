{{-- Results / Metrics --}}
<section class="relative py-24 md:py-36">
    <div class="max-w-7xl mx-auto px-6 md:px-10">
        {{-- Section header --}}
        <div class="max-w-3xl mb-16 md:mb-20 reveal">
            <span class="text-xs font-medium text-cyan tracking-widest uppercase mb-4 inline-block">
                {{ __('landing.results.eyebrow') }}
            </span>
            <h2 class="font-display text-4xl md:text-5xl lg:text-6xl font-semibold tracking-tight leading-[1.1] mb-6">
                {{ __('landing.results.title') }}
            </h2>
            <p class="text-lg text-muted leading-relaxed">
                {{ __('landing.results.subtitle') }}
            </p>
        </div>

        {{-- Metrics grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-white/5 rounded-3xl overflow-hidden border border-white/5">
            @foreach(__('landing.results.metrics') as $i => $metric)
                <div class="reveal p-8 md:p-10 bg-ink-800/80 hover:bg-ink-700/80 transition-all" style="transition-delay: {{ $i * 100 }}ms">
                    <div class="flex items-baseline gap-1 mb-3">
                        <span class="font-display text-5xl md:text-6xl lg:text-7xl font-semibold gradient-text counter" data-target="{{ $metric['value'] }}">0</span>
                        @if(!empty($metric['suffix']))
                            <span class="font-display text-3xl md:text-4xl font-semibold text-cyan">{{ $metric['suffix'] }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-muted leading-snug">{{ $metric['label'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
