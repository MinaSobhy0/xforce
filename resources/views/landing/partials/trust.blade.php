{{-- Trust bar --}}
<section class="relative py-10 border-y border-white/5 bg-ink-800/30">
    <div class="max-w-7xl mx-auto px-6 md:px-10">
        <div class="flex flex-col lg:flex-row items-start lg:items-center gap-6 lg:gap-10">
            <span class="text-sm text-muted tracking-wide uppercase whitespace-nowrap font-medium">
                {{ __('landing.trust.label') }}
            </span>
            <div class="h-px flex-1 bg-gradient-to-r from-white/10 via-white/5 to-transparent hidden lg:block"></div>
            <div class="flex flex-wrap gap-2">
                @foreach(__('landing.trust.chips') as $chip)
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-white/10 bg-white/5 text-xs font-medium text-white/80">
                        <span class="w-1 h-1 rounded-full bg-cyan"></span>
                        {{ $chip }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</section>
