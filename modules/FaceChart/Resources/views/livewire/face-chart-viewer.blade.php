<div x-data="{ activeTab: '2d' }" class="space-y-4">
    {{-- Tab Navigation --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        {{-- 3D Tab - TEMPORARILY DISABLED --}}
        {{--
        <button
            @click="activeTab = '3d'"
            :class="activeTab === '3d' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
            class="px-4 py-2 font-medium border-b-2 transition"
        >
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span>{{ __('face_chart::face_chart.tabs.3d_view') }}</span>
            </div>
        </button>
        --}}

        <button
            @click="activeTab = '2d'; $dispatch('tab-switched-to-2d')"
            :class="activeTab === '2d' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
            class="px-4 py-2 font-medium border-b-2 transition"
        >
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>{{ __('face_chart::face_chart.tabs.2d_view') }}</span>
            </div>
        </button>
    </div>

    {{-- 3D View - TEMPORARILY DISABLED --}}
    {{--
    <div x-show="activeTab === '3d'" x-cloak>
        @livewire('face_chart::face-chart-3d', [
            'patientId' => $patientId,
            'appointmentId' => $appointmentId,
            'editMode' => $editMode,
        ])
    </div>
    --}}

    {{-- 2D View --}}
    <div x-show="activeTab === '2d'">
        @livewire('face_chart::face-chart-2d', [
            'patientId' => $patientId,
            'appointmentId' => $appointmentId,
            'editMode' => $editMode,
        ])
    </div>
</div>
