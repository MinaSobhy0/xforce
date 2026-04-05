<header x-data="{ mobileMenuOpen: false }" class="bg-white shadow-sm sticky top-0 z-50">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            {{-- Logo --}}
            <div class="flex items-center">
                <a href="/" class="flex-shrink-0">
                    @if($settings['logo'] ?? false)
                        <img class="h-10 w-auto" src="{{ website_asset($settings['logo']) }}" alt="{{ $tenant->name ?? '' }}">
                    @else
                        <span class="text-xl font-bold text-primary">{{ $tenant->name ?? config('app.name') }}</span>
                    @endif
                </a>
            </div>

            {{-- Desktop Navigation --}}
            <div class="hidden md:flex md:items-center md:space-x-8 {{ $isRtl ? 'md:space-x-reverse' : '' }}">
                @foreach($headerMenu as $item)
                    @if(!empty($item['children']))
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" @click.away="open = false"
                                class="text-gray-700 hover:text-primary px-3 py-2 text-sm font-medium inline-flex items-center">
                                {{ $item['label'] }}
                                <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" x-transition
                                class="absolute {{ $isRtl ? 'right-0' : 'left-0' }} mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                <div class="py-1">
                                    @foreach($item['children'] as $child)
                                        <a href="{{ $child['url'] }}"
                                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}"
                           class="text-gray-700 hover:text-primary px-3 py-2 text-sm font-medium">
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach

                {{-- Language Switcher --}}
                <div class="flex items-center space-x-2 {{ $isRtl ? 'space-x-reverse' : '' }} border-{{ $isRtl ? 'r' : 'l' }} border-gray-200 {{ $isRtl ? 'pr-4' : 'pl-4' }}">
                    <a href="?lang=en" class="text-sm {{ $locale === 'en' ? 'text-primary font-semibold' : 'text-gray-500 hover:text-gray-700' }}">EN</a>
                    <span class="text-gray-300">|</span>
                    <a href="?lang=ar" class="text-sm {{ $locale === 'ar' ? 'text-primary font-semibold' : 'text-gray-500 hover:text-gray-700' }}">AR</a>
                </div>
            </div>

            {{-- Mobile menu button --}}
            <div class="flex items-center md:hidden">
                <button @click="mobileMenuOpen = !mobileMenuOpen"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-primary hover:bg-gray-100">
                    <svg x-show="!mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    {{-- Mobile Menu --}}
    <div x-show="mobileMenuOpen" x-transition class="md:hidden">
        <div class="px-2 pt-2 pb-3 space-y-1">
            @foreach($headerMenu as $item)
                <a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}"
                   class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-primary hover:bg-gray-50">
                    {{ $item['label'] }}
                </a>
                @if(!empty($item['children']))
                    @foreach($item['children'] as $child)
                        <a href="{{ $child['url'] }}"
                           class="block px-6 py-2 text-sm text-gray-500 hover:text-primary">
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                @endif
            @endforeach

            {{-- Mobile Language Switcher --}}
            <div class="flex items-center space-x-4 px-3 py-2">
                <a href="?lang=en" class="text-sm {{ $locale === 'en' ? 'text-primary font-semibold' : 'text-gray-500' }}">English</a>
                <a href="?lang=ar" class="text-sm {{ $locale === 'ar' ? 'text-primary font-semibold' : 'text-gray-500' }}">العربية</a>
            </div>
        </div>
    </div>
</header>
