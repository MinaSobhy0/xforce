{{-- Solution / Features grid --}}
@php
    $iconPaths = [
        'user-circle' => 'M16 14a4 4 0 10-8 0M12 14a9 9 0 00-9 9h18a9 9 0 00-9-9zM12 14V6a2 2 0 012-2h2',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'banknotes' => 'M3 10h18M3 14h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z',
        'cube' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        'megaphone' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
        'chart-bar' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'device-phone' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
    ];
@endphp

<section id="features" class="relative py-24 md:py-36">
    <div class="max-w-7xl mx-auto px-6 md:px-10">
        {{-- Section header --}}
        <div class="max-w-3xl mb-16 md:mb-20 reveal">
            <span class="text-xs font-medium text-cyan tracking-widest uppercase mb-4 inline-block">
                {{ __('landing.solution.eyebrow') }}
            </span>
            <h2 class="font-display text-4xl md:text-5xl lg:text-6xl font-semibold tracking-tight leading-[1.1] mb-6">
                {{ __('landing.solution.title') }}
            </h2>
            <p class="text-lg text-muted leading-relaxed">
                {{ __('landing.solution.subtitle') }}
            </p>
        </div>

        {{-- Feature grid --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-px bg-white/5 rounded-3xl overflow-hidden border border-white/5">
            @foreach(__('landing.solution.features') as $i => $feature)
                <div class="reveal feature-cell relative p-8 bg-ink-800/80 hover:bg-ink-700/80 transition-all duration-500 group" style="transition-delay: {{ ($i % 4) * 75 }}ms">
                    {{-- Icon --}}
                    <div class="mb-6">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-cyan/10 to-cyan/5 border border-cyan/20 group-hover:border-cyan/40 transition-colors">
                            <svg class="w-6 h-6 text-cyan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPaths[$feature['icon']] ?? $iconPaths['user-circle'] }}"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Content --}}
                    <h3 class="font-display text-lg font-semibold mb-3 tracking-tight">{{ $feature['title'] }}</h3>
                    <p class="text-sm text-muted leading-relaxed">{{ $feature['description'] }}</p>

                    {{-- Subtle corner accent --}}
                    <span class="absolute top-0 {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} w-8 h-px bg-cyan/0 group-hover:bg-cyan/60 transition-colors duration-500"></span>
                    <span class="absolute top-0 {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} h-8 w-px bg-cyan/0 group-hover:bg-cyan/60 transition-colors duration-500"></span>
                </div>
            @endforeach
        </div>
    </div>
</section>
