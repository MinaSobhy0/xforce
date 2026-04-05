<footer class="bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            {{-- Logo & Description --}}
            <div class="col-span-1 md:col-span-2">
                @if($settings['logo'] ?? false)
                    <img class="h-10 w-auto brightness-0 invert" src="{{ Storage::disk('tenant')->url($settings['logo']) }}" alt="{{ $tenant->name ?? '' }}">
                @else
                    <span class="text-2xl font-bold">{{ $tenant->name ?? config('app.name') }}</span>
                @endif

                @php
                    $footerText = $settings['footer_text'] ?? null;
                    $footerTextDisplay = is_array($footerText) ? ($footerText[$locale] ?? $footerText['en'] ?? '') : $footerText;
                @endphp
                @if($footerTextDisplay)
                    <p class="mt-4 text-gray-400 max-w-md">
                        {{ $footerTextDisplay }}
                    </p>
                @endif

                {{-- Social Links --}}
                <div class="mt-6 flex space-x-4 {{ $isRtl ? 'space-x-reverse' : '' }}">
                    @if($settings['social_facebook'] ?? false)
                        <a href="{{ $settings['social_facebook'] }}" target="_blank" class="text-gray-400 hover:text-white">
                            <span class="sr-only">Facebook</span>
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                        </a>
                    @endif
                    @if($settings['social_instagram'] ?? false)
                        <a href="{{ $settings['social_instagram'] }}" target="_blank" class="text-gray-400 hover:text-white">
                            <span class="sr-only">Instagram</span>
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z"/></svg>
                        </a>
                    @endif
                    @if($settings['social_twitter'] ?? false)
                        <a href="{{ $settings['social_twitter'] }}" target="_blank" class="text-gray-400 hover:text-white">
                            <span class="sr-only">Twitter</span>
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"/></svg>
                        </a>
                    @endif
                    @if($settings['social_youtube'] ?? false)
                        <a href="{{ $settings['social_youtube'] }}" target="_blank" class="text-gray-400 hover:text-white">
                            <span class="sr-only">YouTube</span>
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Footer Menu --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-300 tracking-wider uppercase">
                    {{ $locale === 'ar' ? 'روابط سريعة' : 'Quick Links' }}
                </h3>
                <ul class="mt-4 space-y-2">
                    @foreach($footerMenu as $item)
                        <li>
                            <a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}"
                               class="text-gray-400 hover:text-white">
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Contact Info --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-300 tracking-wider uppercase">
                    {{ $locale === 'ar' ? 'اتصل بنا' : 'Contact' }}
                </h3>
                <ul class="mt-4 space-y-2">
                    @if($settings['contact_phone'] ?? false)
                        <li class="flex items-center text-gray-400">
                            <svg class="h-5 w-5 {{ $isRtl ? 'ml-2' : 'mr-2' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <a href="tel:{{ $settings['contact_phone'] }}" class="hover:text-white">{{ $settings['contact_phone'] }}</a>
                        </li>
                    @endif
                    @if($settings['contact_email'] ?? false)
                        <li class="flex items-center text-gray-400">
                            <svg class="h-5 w-5 {{ $isRtl ? 'ml-2' : 'mr-2' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <a href="mailto:{{ $settings['contact_email'] }}" class="hover:text-white">{{ $settings['contact_email'] }}</a>
                        </li>
                    @endif
                    @if($settings['contact_address'] ?? false)
                        <li class="flex items-start text-gray-400">
                            <svg class="h-5 w-5 {{ $isRtl ? 'ml-2' : 'mr-2' }} mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>{{ $settings['contact_address'] }}</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- Copyright --}}
        <div class="mt-12 pt-8 border-t border-gray-800">
            <p class="text-center text-gray-400 text-sm">
                &copy; {{ date('Y') }} {{ $tenant->name ?? config('app.name') }}. {{ $locale === 'ar' ? 'جميع الحقوق محفوظة.' : 'All rights reserved.' }}
            </p>
        </div>
    </div>
</footer>
