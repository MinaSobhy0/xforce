@php
    $layout = $settings['layout'] ?? 'split';
    $showForm = $content['show_form'] ?? true;
    $showMap = $content['show_map'] ?? true;
    $showInfo = $content['show_info'] ?? true;
@endphp

<section class="py-16 lg:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section Header --}}
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                {{ $content['title'][$locale] ?? $content['title']['en'] ?? ($locale === 'ar' ? 'اتصل بنا' : 'Contact Us') }}
            </h2>
        </div>

        <div class="{{ $layout === 'split' ? 'grid grid-cols-1 lg:grid-cols-2 gap-12' : 'max-w-3xl mx-auto' }}">
            {{-- Contact Form --}}
            @if($showForm)
                <div class="bg-white rounded-xl p-8 shadow-sm">
                    <form action="#" method="POST" class="space-y-6">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ $locale === 'ar' ? 'الاسم' : 'Name' }} *
                                </label>
                                <input type="text" id="name" name="name" required
                                       class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ $locale === 'ar' ? 'الهاتف' : 'Phone' }} *
                                </label>
                                <input type="tel" id="phone" name="phone" required
                                       class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $locale === 'ar' ? 'البريد الإلكتروني' : 'Email' }}
                            </label>
                            <input type="email" id="email" name="email"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $locale === 'ar' ? 'الرسالة' : 'Message' }} *
                            </label>
                            <textarea id="message" name="message" rows="4" required
                                      class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>

                        <button type="submit"
                                class="w-full bg-primary text-white font-semibold py-3 px-6 rounded-lg hover:bg-primary/90 transition-colors duration-200">
                            {{ $locale === 'ar' ? 'إرسال الرسالة' : 'Send Message' }}
                        </button>
                    </form>
                </div>
            @endif

            {{-- Contact Info & Map --}}
            <div class="space-y-8">
                @if($showInfo)
                    <div class="bg-white rounded-xl p-8 shadow-sm">
                        <h3 class="text-xl font-semibold text-gray-900 mb-6">
                            {{ $locale === 'ar' ? 'معلومات الاتصال' : 'Contact Information' }}
                        </h3>

                        <div class="space-y-4">
                            @if($settings['contact_phone'] ?? false)
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-primary {{ $locale === 'ar' ? 'ml-4' : 'mr-4' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    <a href="tel:{{ $settings['contact_phone'] }}" class="text-gray-700 hover:text-primary">
                                        {{ $settings['contact_phone'] }}
                                    </a>
                                </div>
                            @endif

                            @if($settings['contact_email'] ?? false)
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-primary {{ $locale === 'ar' ? 'ml-4' : 'mr-4' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <a href="mailto:{{ $settings['contact_email'] }}" class="text-gray-700 hover:text-primary">
                                        {{ $settings['contact_email'] }}
                                    </a>
                                </div>
                            @endif

                            @if($settings['contact_address'] ?? false)
                                <div class="flex items-start">
                                    <svg class="w-6 h-6 text-primary {{ $locale === 'ar' ? 'ml-4' : 'mr-4' }} mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-gray-700">{{ $settings['contact_address'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if($showMap && ($settings['google_maps_embed'] ?? false))
                    <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                        <div class="aspect-video">
                            {!! $settings['google_maps_embed'] !!}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
