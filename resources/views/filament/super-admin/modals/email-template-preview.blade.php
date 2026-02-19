<div class="space-y-6">
    {{-- Language Tabs --}}
    <div x-data="{ activeTab: 'en' }" class="space-y-4">
        <div class="flex space-x-2 border-b border-gray-200 dark:border-gray-700">
            <button
                @click="activeTab = 'en'"
                :class="activeTab === 'en' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
            >
                English
            </button>
            <button
                @click="activeTab = 'ar'"
                :class="activeTab === 'ar' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
            >
                Arabic
            </button>
        </div>

        {{-- English Preview --}}
        <div x-show="activeTab === 'en'" class="space-y-4">
            <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-6">
                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Email Header --}}
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary-500 to-primary-600">
                        <div class="text-white font-bold text-lg">Cairo Glow Clinic</div>
                        <div class="text-primary-100 text-sm">Powered by XLinic</div>
                    </div>

                    {{-- Subject --}}
                    <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        <div class="text-sm text-gray-500">Subject:</div>
                        <div class="font-semibold text-gray-900 dark:text-white">
                            {{ $record->renderSubjectPreview('en') }}
                        </div>
                    </div>

                    {{-- Email Body --}}
                    <div class="px-6 py-6 prose prose-sm dark:prose-invert max-w-none">
                        {!! $record->renderBodyPreview('en') !!}
                    </div>

                    {{-- Email Footer --}}
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 text-center">
                            This email was sent by Cairo Glow Clinic via XLinic.
                            <br>
                            <a href="#" class="text-primary-500 hover:underline">Unsubscribe</a> |
                            <a href="#" class="text-primary-500 hover:underline">Privacy Policy</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Arabic Preview --}}
        <div x-show="activeTab === 'ar'" class="space-y-4" dir="rtl">
            <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-6">
                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Email Header --}}
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary-500 to-primary-600">
                        <div class="text-white font-bold text-lg">عيادة كايرو جلو</div>
                        <div class="text-primary-100 text-sm">مدعوم من ليزرباس</div>
                    </div>

                    {{-- Subject --}}
                    <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        <div class="text-sm text-gray-500">الموضوع:</div>
                        <div class="font-semibold text-gray-900 dark:text-white">
                            {{ $record->renderSubjectPreview('ar') }}
                        </div>
                    </div>

                    {{-- Email Body --}}
                    <div class="px-6 py-6 prose prose-sm dark:prose-invert max-w-none">
                        {!! $record->renderBodyPreview('ar') !!}
                    </div>

                    {{-- Email Footer --}}
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 text-center">
                            تم إرسال هذا البريد بواسطة عيادة كايرو جلو عبر ليزرباس.
                            <br>
                            <a href="#" class="text-primary-500 hover:underline">إلغاء الاشتراك</a> |
                            <a href="#" class="text-primary-500 hover:underline">سياسة الخصوصية</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sample Variables Info --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">
            <x-heroicon-o-information-circle class="w-4 h-4 inline-block mr-1" />
            Sample Data Used in Preview
        </h4>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs text-blue-700 dark:text-blue-300">
            <div><code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">{clinic_name}</code> Cairo Glow Clinic</div>
            <div><code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">{patient_name}</code> Ahmed Hassan</div>
            <div><code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">{appointment_date}</code> Feb 25, 2026</div>
            <div><code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">{appointment_time}</code> 10:30 AM</div>
            <div><code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">{service_name}</code> Laser Hair Removal</div>
            <div><code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">{doctor_name}</code> Dr. Sarah Ahmed</div>
            <div><code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">{invoice_number}</code> INV-2026-0001</div>
            <div><code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">{amount}</code> EGP 1,500.00</div>
            <div><code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">{booking_link}</code> https://...</div>
        </div>
    </div>

    {{-- Template Info --}}
    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 px-2">
        <span><strong>Trigger:</strong> {{ \App\Models\EmailTemplate::TRIGGERS[$record->trigger] ?? $record->trigger }}</span>
        <span><strong>Code:</strong> {{ $record->code }}</span>
        <span><strong>Status:</strong>
            @if($record->is_active)
                <span class="text-green-600 dark:text-green-400">Active</span>
            @else
                <span class="text-red-600 dark:text-red-400">Inactive</span>
            @endif
        </span>
    </div>
</div>
