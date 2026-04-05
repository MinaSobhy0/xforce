@php
    use Modules\Staff\Models\StaffProfile;

    $columns = $settings['columns'] ?? 4;
    $showBio = $content['show_bio'] ?? true;

    // Fetch staff profiles that are public
    $staff = StaffProfile::query()
        ->with('user')
        ->whereHas('user', fn ($q) => $q->where('is_active', true))
        ->where('show_on_website', true)
        ->limit(8)
        ->get();

    $gridClass = match($columns) {
        2 => 'md:grid-cols-2',
        3 => 'md:grid-cols-2 lg:grid-cols-3',
        default => 'md:grid-cols-2 lg:grid-cols-4',
    };
@endphp

<section class="py-16 lg:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Section Header --}}
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                {{ $content['title'][$locale] ?? $content['title']['en'] ?? ($locale === 'ar' ? 'تعرف على فريقنا' : 'Meet Our Team') }}
            </h2>
            @if($content['subtitle'][$locale] ?? $content['subtitle']['en'] ?? false)
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    {{ $content['subtitle'][$locale] ?? $content['subtitle']['en'] }}
                </p>
            @endif
        </div>

        {{-- Team Grid --}}
        <div class="grid grid-cols-1 {{ $gridClass }} gap-8">
            @foreach($staff as $member)
                <div class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-200 text-center">
                    <div class="aspect-square overflow-hidden bg-gray-100">
                        @if($member->photo_url)
                            <img src="{{ Storage::disk('tenant')->url($member->photo_url) }}"
                                 alt="{{ $member->user?->full_name }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary/10 to-secondary/10">
                                <svg class="w-24 h-24 text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ $member->user?->full_name ?? $member->employee_code }}
                        </h3>

                        @if($member->job_title)
                            <p class="text-primary font-medium mt-1">
                                {{ $member->getTranslation('job_title', $locale) ?? $member->getTranslation('job_title', 'en') ?? $member->job_title }}
                            </p>
                        @endif

                        @if($showBio && $member->bio)
                            <p class="text-gray-600 mt-3 text-sm line-clamp-3">
                                {{ $member->getTranslation('bio', $locale) ?? $member->getTranslation('bio', 'en') ?? $member->bio }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
