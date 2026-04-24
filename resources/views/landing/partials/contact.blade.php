{{-- Contact / Demo request --}}
<section id="contact" class="relative py-24 md:py-36 bg-gradient-to-b from-ink-900 to-ink-800">
    <div class="max-w-7xl mx-auto px-6 md:px-10">
        <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-start">
            {{-- Left: copy --}}
            <div class="reveal lg:sticky lg:top-28">
                <span class="text-xs font-medium text-cyan tracking-widest uppercase mb-4 inline-block">
                    {{ __('landing.contact.eyebrow') }}
                </span>
                <h2 class="font-display text-4xl md:text-5xl lg:text-6xl font-semibold tracking-tight leading-[1.05] mb-6">
                    {{ __('landing.contact.title') }}
                </h2>
                <p class="text-lg text-muted leading-relaxed mb-10">
                    {{ __('landing.contact.subtitle') }}
                </p>

                {{-- Trust markers --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-3 text-sm">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-cyan/10 border border-cyan/20">
                            <svg class="w-4 h-4 text-cyan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-white/80">{{ app()->getLocale() === 'ar' ? 'ردّ خلال يوم عمل واحد' : 'Response within 1 business day' }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-cyan/10 border border-cyan/20">
                            <svg class="w-4 h-4 text-cyan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-white/80">{{ app()->getLocale() === 'ar' ? 'عرض مخصص لعيادتك' : 'Walkthrough tailored to your clinic' }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-cyan/10 border border-cyan/20">
                            <svg class="w-4 h-4 text-cyan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-white/80">{{ app()->getLocale() === 'ar' ? 'دون التزامات. دون ضغط.' : 'No commitments. No pressure.' }}</span>
                    </div>
                </div>
            </div>

            {{-- Right: form --}}
            <div class="reveal">
                <div class="relative p-8 md:p-10 rounded-2xl border border-white/10 bg-ink-800/60 backdrop-blur-sm">
                    {{-- Success / error banners --}}
                    @if(session('success'))
                        <div class="mb-6 p-4 rounded-lg border border-cyan/30 bg-cyan/10 text-sm text-cyan flex items-start gap-3">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ __('landing.contact.success') }}</span>
                        </div>
                    @endif
                    @if(isset($errors) && $errors->any())
                        <div class="mb-6 p-4 rounded-lg border border-coral/30 bg-coral/10 text-sm text-coral">
                            <ul class="space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form id="contact-form" method="POST" action="{{ route('contact.submit') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="recaptcha_token" id="recaptcha_token">

                        {{-- Clinic name --}}
                        <div>
                            <label for="clinic_name" class="block text-xs font-medium text-muted uppercase tracking-wider mb-2">
                                {{ __('landing.contact.form.clinic_name') }} <span class="text-coral">*</span>
                            </label>
                            <input type="text" name="clinic_name" id="clinic_name" required value="{{ old('clinic_name') }}"
                                class="w-full px-4 py-3 rounded-lg bg-ink-900/60 border border-white/10 text-white placeholder-muted/50 focus:outline-none focus:border-cyan focus:ring-1 focus:ring-cyan transition-colors">
                        </div>

                        <div class="grid sm:grid-cols-2 gap-5">
                            {{-- Contact name --}}
                            <div>
                                <label for="contact_name" class="block text-xs font-medium text-muted uppercase tracking-wider mb-2">
                                    {{ __('landing.contact.form.contact_name') }} <span class="text-coral">*</span>
                                </label>
                                <input type="text" name="contact_name" id="contact_name" required value="{{ old('contact_name') }}"
                                    class="w-full px-4 py-3 rounded-lg bg-ink-900/60 border border-white/10 text-white placeholder-muted/50 focus:outline-none focus:border-cyan focus:ring-1 focus:ring-cyan transition-colors">
                            </div>

                            {{-- Email --}}
                            <div>
                                <label for="email" class="block text-xs font-medium text-muted uppercase tracking-wider mb-2">
                                    {{ __('landing.contact.form.email') }}
                                </label>
                                <input type="email" name="email" id="email" value="{{ old('email') }}"
                                    class="w-full px-4 py-3 rounded-lg bg-ink-900/60 border border-white/10 text-white placeholder-muted/50 focus:outline-none focus:border-cyan focus:ring-1 focus:ring-cyan transition-colors">
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-5">
                            {{-- Phone --}}
                            <div>
                                <label for="phone" class="block text-xs font-medium text-muted uppercase tracking-wider mb-2">
                                    {{ __('landing.contact.form.phone') }} <span class="text-coral">*</span>
                                </label>
                                <input type="tel" name="phone" id="phone" required value="{{ old('phone') }}"
                                    class="w-full px-4 py-3 rounded-lg bg-ink-900/60 border border-white/10 text-white placeholder-muted/50 focus:outline-none focus:border-cyan focus:ring-1 focus:ring-cyan transition-colors">
                            </div>

                            {{-- Country --}}
                            <div>
                                <label for="country" class="block text-xs font-medium text-muted uppercase tracking-wider mb-2">
                                    {{ __('landing.contact.form.country') }} <span class="text-coral">*</span>
                                </label>
                                <select name="country" id="country" required
                                    class="w-full px-4 py-3 rounded-lg bg-ink-900/60 border border-white/10 text-white focus:outline-none focus:border-cyan focus:ring-1 focus:ring-cyan transition-colors">
                                    <option value="">{{ __('landing.contact.form.country_select') }}</option>
                                    @foreach(__('landing.countries') as $code => $name)
                                        <option value="{{ $code }}" {{ old('country') === $code ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Message --}}
                        <div>
                            <label for="message" class="block text-xs font-medium text-muted uppercase tracking-wider mb-2">
                                {{ __('landing.contact.form.message') }}
                            </label>
                            <textarea name="message" id="message" rows="4"
                                class="w-full px-4 py-3 rounded-lg bg-ink-900/60 border border-white/10 text-white placeholder-muted/50 focus:outline-none focus:border-cyan focus:ring-1 focus:ring-cyan transition-colors resize-none">{{ old('message') }}</textarea>
                        </div>

                        {{-- Submit --}}
                        <button type="submit" id="submit-btn"
                            class="w-full group inline-flex items-center justify-center gap-2 px-6 py-4 rounded-lg bg-white text-ink-900 font-semibold text-sm hover:bg-white/90 transition-all shadow-[0_0_0_0_rgba(0,212,255,0.4)] hover:shadow-[0_0_40px_0_rgba(0,212,255,0.4)] disabled:opacity-60 disabled:cursor-not-allowed">
                            <span data-default-text>{{ __('landing.contact.form.submit') }}</span>
                            <span data-loading-text class="hidden">{{ __('landing.contact.form.submitting') }}</span>
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-1 {{ app()->getLocale() === 'ar' ? 'rotate-180 group-hover:-translate-x-1' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 12h15"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
