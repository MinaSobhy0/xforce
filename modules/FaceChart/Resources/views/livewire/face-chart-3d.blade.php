<div
    x-data="faceChart3D({
        markers: @js($markers),
        isEditing: @js($isEditing),
        appointmentId: @js($appointmentId),
        modelPath: '{{ config('face_chart.model_path', '/models/face_head.glb') }}',
        historicalOpacity: {{ config('face_chart.historical_marker_opacity', 0.6) }},
    })"
    x-init="init()"
    x-on:livewire:navigating.window="destroy()"
    class="min-h-[600px] w-full"
    style="width: 100%;"
    wire:ignore
>
    {{-- Main Layout --}}
    <div class="flex flex-col lg:flex-row gap-4 h-full w-full">

        {{-- 3D Viewer - 70% width --}}
        <div class="w-full min-h-[500px] lg:min-h-[600px]" style="flex: 0 0 70%; max-width: 70%;">
            <div class="bg-gray-900 rounded-lg overflow-hidden h-full relative w-full">

                {{-- Loading Overlay --}}
                <div
                    x-show="loading"
                    x-transition
                    class="absolute inset-0 bg-gray-900 flex items-center justify-center z-10"
                    :style="loading ? '' : 'pointer-events: none'"
                >
                    <div class="text-center">
                        <svg class="animate-spin h-12 w-12 text-primary-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-400">{{ __('face_chart::face_chart.viewer.loading') }}</p>
                    </div>
                </div>

                {{-- Error Message --}}
                <div
                    x-show="error"
                    x-cloak
                    class="absolute inset-0 bg-gray-900 flex items-center justify-center z-10"
                    :style="error ? '' : 'pointer-events: none'"
                >
                    <div class="text-center text-red-400">
                        <svg class="h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p x-text="error"></p>
                    </div>
                </div>

                {{-- Three.js Canvas Container --}}
                <div
                    x-ref="canvas"
                    @click="onCanvasContainerClick($event)"
                    class="w-full h-full min-h-[500px]"
                    style="min-height: 500px; width: 100%; position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 1;"
                ></div>

                {{-- Controls Overlay --}}
                <div class="absolute top-4 left-4 flex flex-col gap-2" style="z-index: 15;">
                    <button
                        @click.stop="resetCamera()"
                        type="button"
                        class="bg-gray-800/80 hover:bg-gray-700 text-white p-2 rounded-lg transition"
                        title="{{ __('face_chart::face_chart.viewer.controls.reset') }}"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                    <button @click.stop="zoomIn()" type="button" class="bg-gray-800/80 hover:bg-gray-700 text-white p-2 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                    </button>
                    <button @click.stop="zoomOut()" type="button" class="bg-gray-800/80 hover:bg-gray-700 text-white p-2 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                        </svg>
                    </button>
                </div>

                {{-- Edit Mode Toggle --}}
                @if($appointmentId)
                <div class="absolute top-4 right-4" style="z-index: 15;">
                    <button
                        wire:click="toggleEditMode"
                        @click.stop
                        type="button"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg transition"
                        :class="isEditing ? 'bg-primary-500 text-white' : 'bg-gray-800/80 hover:bg-gray-700 text-white'"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <span x-text="isEditing ? '{{ __('face_chart::face_chart.viewer.edit_mode') }}' : '{{ __('face_chart::face_chart.viewer.view_mode') }}'"></span>
                    </button>
                </div>
                @endif

                {{-- Edit Mode Instructions --}}
                <div
                    x-show="isEditing && !isDrawingArrow"
                    x-transition
                    class="absolute bottom-4 left-4 right-4 bg-primary-500/90 text-white px-4 py-3 rounded-lg"
                    style="z-index: 15; pointer-events: none;"
                >
                    <div class="flex items-center justify-center gap-6 text-sm">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                            </svg>
                            <span>{{ __('face_chart::face_chart.viewer.click_to_add') }}</span>
                        </div>
                        <div class="w-px h-4 bg-white/40"></div>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>{{ __('face_chart::face_chart.viewer.drag_to_draw') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Drawing Arrow Indicator --}}
                <div
                    x-show="isDrawingArrow"
                    x-transition
                    class="absolute bottom-4 left-4 right-4 bg-teal-600/95 text-white px-4 py-3 rounded-lg"
                    style="z-index: 15; pointer-events: none;"
                >
                    <div class="flex items-center justify-center gap-3 text-sm">
                        <svg class="w-5 h-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>{{ __('face_chart::face_chart.viewer.drawing_arrow') }}</span>
                        <span class="text-white/70">|</span>
                        <span class="text-white/80 text-xs">{{ __('face_chart::face_chart.viewer.press_esc') }}</span>
                    </div>
                </div>

                {{-- Marker Tooltip --}}
                <div
                    x-show="hoveredMarker"
                    x-transition
                    class="absolute bg-gray-800 text-white text-sm rounded-lg p-3 shadow-lg max-w-xs"
                    style="z-index: 25; pointer-events: none;"
                    :style="tooltipStyle"
                >
                    <template x-if="hoveredMarker">
                        <div>
                            {{-- Service/Category name --}}
                            <template x-if="hoveredMarker.serviceName">
                                <div class="text-primary-400 text-xs font-medium" x-text="hoveredMarker.serviceName"></div>
                            </template>
                            <template x-if="!hoveredMarker.serviceName && hoveredMarker.categoryName">
                                <div class="text-primary-400 text-xs font-medium" x-text="hoveredMarker.categoryName"></div>
                            </template>

                            {{-- Product name or type --}}
                            <div class="font-semibold" x-text="hoveredMarker.product || hoveredMarker.type"></div>

                            {{-- Units/Dosage --}}
                            <template x-if="hoveredMarker.units">
                                <div class="text-gray-300 text-xs">
                                    <span x-text="hoveredMarker.units"></span>
                                    <span x-text="hoveredMarker.unitType || 'units'"></span>
                                </div>
                            </template>
                            <template x-if="!hoveredMarker.units && hoveredMarker.dosage">
                                <div class="text-gray-300 text-xs" x-text="hoveredMarker.dosage"></div>
                            </template>

                            {{-- Date --}}
                            <div class="text-gray-400 text-xs mt-1" x-text="hoveredMarker.date"></div>
                        </div>
                    </template>
                </div>

                {{-- Add/Edit Marker Modal --}}
                <div
                    x-show="showMarkerModal"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 bg-black/50 flex items-center justify-center"
                    style="z-index: 50;"
                    @click.self="cancelMarker()"
                >
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto"
                        @click.stop
                    >
                        {{-- Modal Header --}}
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <template x-if="!isEditingMarker">
                                    <svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </template>
                                <template x-if="isEditingMarker">
                                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </template>
                                <span x-text="isEditingMarker ? '{{ __('face_chart::face_chart.marker.edit') }}' : '{{ __('face_chart::face_chart.marker.add') }}'"></span>
                            </h3>
                            <button @click="cancelMarker()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Read-only badge for historical markers --}}
                        <template x-if="isEditingMarker && !pendingMarker.is_editable">
                            <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg">
                                <div class="flex items-center gap-2 text-amber-800 dark:text-amber-200 text-sm">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>{{ __('face_chart::face_chart.messages.cannot_edit_historical') }}</span>
                                </div>
                            </div>
                        </template>

                        {{-- Marker Info for editing --}}
                        <template x-if="isEditingMarker && pendingMarker.performed_at">
                            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-sm space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.fields.performed_at') }}:</span>
                                    <span class="text-gray-900 dark:text-white" x-text="pendingMarker.performed_at"></span>
                                </div>
                                <template x-if="pendingMarker.performed_by_name">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.fields.performed_by') }}:</span>
                                        <span class="text-gray-900 dark:text-white" x-text="pendingMarker.performed_by_name"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div class="space-y-4">
                            {{-- Region Display --}}
                            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.fields.face_region') }}:</span>
                                <span class="font-medium text-gray-900 dark:text-white ml-2" x-text="pendingMarker.regionLabel || pendingMarker.region || 'Unknown'"></span>
                            </div>

                            {{-- Product Name --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ __('face_chart::face_chart.fields.product_name') }}
                                </label>
                                <input
                                    type="text"
                                    x-model="pendingMarker.product_name"
                                    :disabled="isEditingMarker && !pendingMarker.is_editable"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    placeholder="e.g., Botox, Dysport, Juvederm"
                                >
                            </div>

                            {{-- Units --}}
                            <div class="flex gap-3">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('face_chart::face_chart.fields.units') }}
                                    </label>
                                    <input
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        x-model="pendingMarker.units"
                                        :disabled="isEditingMarker && !pendingMarker.is_editable"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        placeholder="0"
                                    >
                                </div>
                                <div class="w-28">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('face_chart::face_chart.fields.unit_type') }}
                                    </label>
                                    <select
                                        x-model="pendingMarker.unit_type"
                                        :disabled="isEditingMarker && !pendingMarker.is_editable"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <option value="units">Units</option>
                                        <option value="ml">mL</option>
                                        <option value="cc">cc</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Direction/Angle --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ __('face_chart::face_chart.fields.direction') }}
                                </label>

                                {{-- Visual instruction for drag-to-draw --}}
                                <template x-if="!isEditingMarker || pendingMarker.is_editable">
                                    <div class="mb-3 p-3 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 rounded-lg">
                                        <div class="flex items-start gap-2">
                                            <svg class="w-5 h-5 text-primary-600 dark:text-primary-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-primary-800 dark:text-primary-200">
                                                    {{ __('face_chart::face_chart.fields.drag_instruction_title') }}
                                                </p>
                                                <p class="text-xs text-primary-600 dark:text-primary-400 mt-0.5">
                                                    {{ __('face_chart::face_chart.fields.drag_instruction_desc') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                {{-- Current direction indicator (if set) --}}
                                <template x-if="pendingMarker.direction_x !== null && pendingMarker.direction_y !== null && pendingMarker.direction_z !== null">
                                    <div class="mb-3 p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg flex items-center gap-3">
                                        <div class="flex items-center justify-center w-10 h-10 bg-teal-100 dark:bg-teal-900/50 rounded-full">
                                            <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="currentColor"
                                                :style="'transform: rotate(' + (Math.atan2(pendingMarker.direction_x, -pendingMarker.direction_y) * 180 / Math.PI) + 'deg)'">
                                                <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ __('face_chart::face_chart.fields.direction_set') }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <template x-if="pendingMarker.depth_mm">
                                                    <span>{{ __('face_chart::face_chart.fields.depth') }}: <span x-text="pendingMarker.depth_mm"></span>mm</span>
                                                </template>
                                            </p>
                                        </div>
                                        <button
                                            @click="pendingMarker.direction_x = null; pendingMarker.direction_y = null; pendingMarker.direction_z = null; pendingMarker.depth_mm = ''; pendingMarker.direction_preset = '';"
                                            x-show="!isEditingMarker || pendingMarker.is_editable"
                                            type="button"
                                            class="p-1 text-gray-400 hover:text-red-500 transition"
                                            title="{{ __('face_chart::face_chart.fields.clear_direction') }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>

                                {{-- Fallback direction preset dropdown (collapsed by default) --}}
                                <details class="text-sm">
                                    <summary class="cursor-pointer text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                        {{ __('face_chart::face_chart.fields.use_preset') }}
                                    </summary>
                                    <div class="mt-2 flex gap-3">
                                        <select
                                            x-model="pendingMarker.direction_preset"
                                            @change="applyDirectionPreset()"
                                            :disabled="isEditingMarker && !pendingMarker.is_editable"
                                            class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                                        >
                                            <option value="">-- Select Direction --</option>
                                            <option value="perpendicular">Perpendicular (90°)</option>
                                            <option value="angled_down">Angled Down (45°)</option>
                                            <option value="angled_up">Angled Up (45°)</option>
                                            <option value="horizontal">Horizontal</option>
                                            <option value="custom">Custom</option>
                                        </select>
                                        <div class="w-20">
                                            <input
                                                type="number"
                                                step="1"
                                                min="1"
                                                max="20"
                                                x-model="pendingMarker.depth_mm"
                                                :disabled="isEditingMarker && !pendingMarker.is_editable"
                                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                                                placeholder="mm"
                                                title="{{ __('face_chart::face_chart.fields.depth') }}"
                                            >
                                        </div>
                                    </div>
                                </details>
                            </div>

                            {{-- Notes --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ __('face_chart::face_chart.fields.notes') }}
                                </label>
                                <textarea
                                    x-model="pendingMarker.notes"
                                    rows="2"
                                    :disabled="isEditingMarker && !pendingMarker.is_editable"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                    placeholder="{{ __('face_chart::face_chart.fields.notes_placeholder') }}"
                                ></textarea>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="mt-6 space-y-3">
                            {{-- Delete button (only for editable markers in current session) --}}
                            <button
                                @click="deleteMarkerFromModal()"
                                type="button"
                                :style="(isEditingMarker && pendingMarker.is_editable) ? 'display: flex; background-color: #ef4444; color: white; width: 100%; padding: 10px 16px; border-radius: 8px; font-weight: 500; align-items: center; justify-content: center; gap: 8px;' : 'display: none;'"
                                class="hover:bg-red-600 transition"
                            >
                                <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                {{ __('face_chart::face_chart.marker.delete') }}
                            </button>

                            {{-- Save/Cancel buttons row --}}
                            <div class="flex gap-3">
                                <button
                                    @click="cancelMarker()"
                                    type="button"
                                    class="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium"
                                >
                                    {{ __('face_chart::face_chart.marker.cancel') }}
                                </button>

                                {{-- Save button (only if editable or new) --}}
                                <button
                                    x-show="!isEditingMarker || pendingMarker.is_editable"
                                    @click="saveMarker()"
                                    type="button"
                                    class="flex-1 px-4 py-2.5 bg-primary-500 hover:bg-primary-600 text-white rounded-lg transition font-medium flex items-center justify-center gap-2"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ __('face_chart::face_chart.marker.save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Sidebar - 30% width --}}
        <div class="w-full flex flex-col gap-4" style="flex: 0 0 30%; max-width: 30%;">

            {{-- New Marker Form --}}
            @if($appointmentId)
            <div x-show="isEditing" x-transition class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                    {{ __('face_chart::face_chart.marker.new') }}
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('face_chart::face_chart.fields.marker_type') }}
                        </label>
                        <select wire:model.live="newMarker.marker_type" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach($this->markerTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('face_chart::face_chart.fields.product_name') }}
                        </label>
                        <input type="text" wire:model="newMarker.product_name" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="e.g., Botox, Juvederm">
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('face_chart::face_chart.fields.units') }}
                            </label>
                            <input type="number" step="0.1" wire:model="newMarker.units" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div class="w-24">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('face_chart::face_chart.fields.unit_type') }}
                            </label>
                            <select wire:model="newMarker.unit_type" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                @foreach($this->unitTypeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('face_chart::face_chart.fields.color') }}
                        </label>
                        <input type="color" wire:model="newMarker.color" class="w-full h-10 rounded-lg border-gray-300 dark:border-gray-600 cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('face_chart::face_chart.fields.notes') }}
                        </label>
                        <textarea wire:model="newMarker.notes" rows="2" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                </div>
            </div>
            @endif

            {{-- Selected Marker Details --}}
            @if($selectedMarker)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-2 border-green-500">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __('face_chart::face_chart.marker.details') }}
                    </h3>
                    <button wire:click="selectMarker(null)" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="space-y-3 text-sm">
                    {{-- Product Name --}}
                    @if($selectedMarker['product_name'])
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.fields.product_name') }}:</span>
                        <span class="text-gray-900 dark:text-white font-medium">{{ $selectedMarker['product_name'] }}</span>
                    </div>
                    @endif

                    {{-- Marker Type --}}
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.fields.marker_type') }}:</span>
                        <span class="text-gray-900 dark:text-white">{{ __("face_chart::face_chart.marker_types.{$selectedMarker['marker_type']}") }}</span>
                    </div>

                    {{-- Region --}}
                    @if($selectedMarker['face_region'])
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.fields.face_region') }}:</span>
                        <span class="text-gray-900 dark:text-white">{{ __("face_chart::face_chart.regions.{$selectedMarker['face_region']}") }}</span>
                    </div>
                    @endif

                    {{-- Units/Dosage --}}
                    @if($selectedMarker['units'])
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.fields.units') }}:</span>
                        <span class="text-gray-900 dark:text-white font-medium">{{ $selectedMarker['units'] }} {{ $selectedMarker['unit_type'] }}</span>
                    </div>
                    @endif

                    {{-- Date --}}
                    @if($selectedMarker['performed_at'])
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.fields.performed_at') }}:</span>
                        <span class="text-gray-900 dark:text-white">{{ $selectedMarker['performed_at'] }}</span>
                    </div>
                    @endif

                    {{-- Performed By --}}
                    @if($selectedMarker['performed_by_name'])
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.fields.performed_by') }}:</span>
                        <span class="text-gray-900 dark:text-white">{{ $selectedMarker['performed_by_name'] }}</span>
                    </div>
                    @endif

                    {{-- Notes --}}
                    @if($selectedMarker['notes'])
                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-gray-500 dark:text-gray-400 block mb-1">{{ __('face_chart::face_chart.fields.notes') }}:</span>
                        <p class="text-gray-900 dark:text-white text-xs bg-gray-50 dark:bg-gray-700 p-2 rounded">{{ $selectedMarker['notes'] }}</p>
                    </div>
                    @endif

                    {{-- Editable Status Badge --}}
                    <div class="pt-2">
                        @if($selectedMarker['is_editable'])
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            {{ __('face_chart::face_chart.legend.current') }}
                        </span>
                        @else
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            {{ __('face_chart::face_chart.legend.historical') }}
                        </span>
                        @endif
                    </div>

                    {{-- Action Buttons --}}
                    @if($selectedMarker['is_editable'])
                    <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            wire:click="deleteSelectedMarker"
                            wire:confirm="{{ __('face_chart::face_chart.confirm.delete_marker') }}"
                            type="button"
                            class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2.5 px-4 rounded-lg transition flex items-center justify-center gap-2 font-medium"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            {{ __('face_chart::face_chart.marker.delete') }}
                        </button>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Filters --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                    {{ __('face_chart::face_chart.filters.title') }}
                </h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('face_chart::face_chart.filters.date_from') }}</label>
                            <input type="date" wire:model="dateFrom" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('face_chart::face_chart.filters.date_to') }}</label>
                            <input type="date" wire:model="dateTo" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('face_chart::face_chart.filters.region') }}</label>
                        <select wire:model="regionFilter" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">{{ __('face_chart::face_chart.filters.all_regions') }}</option>
                            @foreach($this->regionOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="applyFilters" type="button" class="flex-1 bg-primary-500 hover:bg-primary-600 text-white py-2 px-4 rounded-lg transition text-sm">
                            {{ __('face_chart::face_chart.filters.apply') }}
                        </button>
                        <button wire:click="clearFilters" type="button" class="px-4 py-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition text-sm">
                            {{ __('face_chart::face_chart.filters.clear') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Legend --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <h3 class="text-sm font-semibold mb-3 text-gray-900 dark:text-white">{{ __('face_chart::face_chart.legend.title') }}</h3>
                <div class="space-y-3 text-sm">
                    {{-- Session status --}}
                    <div class="pb-2 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-3 h-3 rounded-full bg-gray-400 opacity-60"></span>
                            <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.legend.historical') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-primary-500"></span>
                            <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.legend.current') }}</span>
                        </div>
                    </div>
                    {{-- Marker shapes by category --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L8 12h8L12 2z"/>
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.legend.injection') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L4 12l8 10 8-10L12 2z"/>
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.legend.laser') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-red-500 inline-block"></span>
                            <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.legend.filler') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-purple-500" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="10" y="4" width="4" height="16" rx="1"/>
                            </svg>
                            <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.legend.thread') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Statistics --}}
            @if(count($markers) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <h3 class="text-sm font-semibold mb-3 text-gray-900 dark:text-white">{{ __('face_chart::face_chart.stats.title') }}</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.stats.total_markers') }}</span>
                        <p class="text-xl font-semibold text-gray-900 dark:text-white">{{ count($markers) }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('face_chart::face_chart.stats.total_units') }}</span>
                        <p class="text-xl font-semibold text-gray-900 dark:text-white">{{ collect($markers)->sum('units') ?: '-' }}</p>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>

@assets
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js?v=3"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js?v=3"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js?v=3"></script>
@endassets

@script
<script>
Alpine.data('faceChart3D', function(config) {
    // Private variables - NOT reactive (Three.js objects must NOT be in Alpine reactive data)
    let scene, camera, renderer, controls, faceModel, raycaster, mouse;
    let markerMeshes = [];
    let animationId = null;
    let isDestroyed = false;

    // Texture loader and cache for PNG icons
    let textureLoader = null;
    let textureCache = {};

    // Arrow drawing state - private (Three.js objects)
    let previewArrowGroup = null;
    let drawStartPoint = null;
    let drawCurrentPoint = null;
    let drawingMarkerPosition = null;
    let drawingSurfaceNormal = null;  // Surface normal at marker point

    // Icon paths configuration
    const iconPaths = {
        'injection': '/images/markers/injection.png',
        'syringe': '/images/markers/injection.png',
        'laser': '/images/markers/laser.png',
        'laser_spot': '/images/markers/laser.png',
        'filler': '/images/markers/filler.png',
        'filler_point': '/images/markers/filler.png',
        'thread': '/images/markers/thread.png',
        'thread_anchor': '/images/markers/thread.png',
        'marking': '/images/markers/marking.png',
        'default': '/images/markers/injection.png',
    };

    // Region labels for display
    const regionLabels = {
        'forehead': 'Forehead',
        'glabella': 'Glabella',
        'temples': 'Temples',
        'crow_feet': "Crow's Feet",
        'upper_eyelid': 'Upper Eyelid',
        'lower_eyelid': 'Lower Eyelid',
        'nose': 'Nose',
        'cheeks': 'Cheeks',
        'nasolabial': 'Nasolabial Folds',
        'upper_lip': 'Upper Lip',
        'lower_lip': 'Lower Lip',
        'marionette': 'Marionette Lines',
        'chin': 'Chin',
        'jawline': 'Jawline',
        'neck': 'Neck',
    };

    return {
        loading: true,
        error: null,
        isEditing: config.isEditing || false,
        hoveredMarker: null,
        tooltipStyle: '',

        // Arrow drawing state (drag-to-draw) - only primitive values in Alpine reactive data
        isDrawingArrow: false,
        drawingMarkerId: null,

        // Modal state
        showMarkerModal: false,
        isEditingMarker: false,  // true = editing existing, false = adding new
        editingMarkerId: null,   // ID of marker being edited
        pendingMarker: {
            x: 0,
            y: 0,
            z: 0,
            region: '',
            regionLabel: '',
            product_name: '',
            units: '',
            unit_type: 'units',
            direction_preset: '',
            direction_x: null,
            direction_y: null,
            direction_z: null,
            depth_mm: '',
            notes: '',
            is_editable: true,
            performed_at: '',
            performed_by_name: '',
        },

        init() {
            console.log('[FC3D] Init v27 - Arrows perpendicular to face surface');
            // Prevent re-initialization
            if (this.$el._fc3dInit) {
                console.log('[FC3D] Already initialized, skipping');
                return;
            }
            this.$el._fc3dInit = true;

            // Delay setup to ensure DOM is fully rendered
            setTimeout(() => this.setup(), 200);

            // Listen for Livewire events
            this.$wire.$on('markersLoaded', (data) => {
                this.renderMarkers(JSON.parse(JSON.stringify(data.markers || [])));
            });
            this.$wire.$on('editModeChanged', (data) => {
                this.isEditing = !!data.isEditing;
            });
            // Listen for marker data when editing
            this.$wire.$on('markerDataLoaded', (data) => {
                console.log('[FC3D] markerDataLoaded event:', data);
                if (data.marker) {
                    this.populateEditModal(data.marker);
                }
            });
        },

        // Debug click handler on container div (Alpine event)
        onCanvasContainerClick(e) {
            console.log('[FC3D] Container click captured!', e.target.tagName);
            // If the click is on the canvas element, let it pass through to the Three.js handler
            // The onClick method will be called by the renderer's event listener
        },

        setup() {
            console.log('[FC3D] Setup called');
            const container = this.$refs.canvas;
            if (!container || isDestroyed) {
                console.log('[FC3D] No container or destroyed');
                return;
            }

            // Debug: Check if container is in the DOM and visible
            console.log('[FC3D] Container element:', container);
            console.log('[FC3D] Container in DOM:', document.body.contains(container));
            console.log('[FC3D] Container computed style display:', getComputedStyle(container).display);

            // Try to get dimensions from container or parent
            let width = container.clientWidth || container.offsetWidth;
            let height = container.clientHeight || container.offsetHeight;

            // If still no width, try parent elements
            if (width < 50) {
                let parent = container.parentElement;
                while (parent && width < 50) {
                    width = parent.clientWidth || parent.offsetWidth;
                    parent = parent.parentElement;
                }
            }

            console.log('[FC3D] Container size:', width, 'x', height);

            // If still no width after checking parents, use default or wait
            if (width < 50) {
                // Try one more time after a longer delay
                if (!this._setupRetries) this._setupRetries = 0;
                this._setupRetries++;

                if (this._setupRetries < 20) {
                    console.log('[FC3D] Container too small, retry', this._setupRetries);
                    setTimeout(() => this.setup(), 100);
                    return;
                } else {
                    // Give up waiting, use default
                    console.log('[FC3D] Using default dimensions');
                    width = 800;
                }
            }

            this.initScene(container, width, height);
            this.loadModel(config.modelPath);
            this.animate();
        },

        initScene(container, width, height) {
            const w = width || container.clientWidth || 800;
            const h = height || container.clientHeight || 500;
            console.log('[FC3D] initScene with dimensions:', w, 'x', h);

            scene = new THREE.Scene();
            scene.background = new THREE.Color(0x1f2937);

            camera = new THREE.PerspectiveCamera(50, w / h, 0.1, 1000);
            camera.position.set(0, 0, 2.5);

            renderer = new THREE.WebGLRenderer({ antialias: true });
            renderer.setSize(w, h);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            renderer.outputEncoding = THREE.sRGBEncoding;

            // Clear container and add canvas
            container.innerHTML = '';
            container.appendChild(renderer.domElement);

            controls = new THREE.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            controls.minDistance = 1;
            controls.maxDistance = 5;

            // Lighting
            scene.add(new THREE.AmbientLight(0xffffff, 0.7));
            const light1 = new THREE.DirectionalLight(0xffffff, 0.8);
            light1.position.set(5, 5, 5);
            scene.add(light1);
            const light2 = new THREE.DirectionalLight(0xffffff, 0.4);
            light2.position.set(-5, 0, -5);
            scene.add(light2);

            raycaster = new THREE.Raycaster();
            // Set threshold for sprite intersection (important for clicking on PNG icons)
            raycaster.params.Sprite = { threshold: 0.1 };
            mouse = new THREE.Vector2();

            // Initialize texture loader for PNG icons
            textureLoader = new THREE.TextureLoader();

            // Events - using named function for better debugging
            const canvasElement = renderer.domElement;
            console.log('[FC3D] Attaching click event to canvas element:', canvasElement);

            // Ensure canvas can receive pointer events
            canvasElement.style.pointerEvents = 'auto';
            canvasElement.style.cursor = 'crosshair'; // Visual indicator that canvas is interactive
            canvasElement.style.touchAction = 'none'; // Prevent touch scrolling interference

            // Use pointer events (more reliable than mouse events)
            canvasElement.addEventListener('pointerdown', (e) => {
                console.log('[FC3D] Pointerdown on canvas at', e.clientX, e.clientY, 'type:', e.pointerType);
                this._pointerDownTime = Date.now();
                this._pointerDownPos = { x: e.clientX, y: e.clientY };

                // Check if clicking on a marker to potentially start arrow drawing
                if (this.isEditing) {
                    this.handlePointerDown(e);
                }
            });

            canvasElement.addEventListener('pointermove', (e) => {
                // Handle arrow drawing preview
                if (this.isDrawingArrow && drawStartPoint) {
                    this.handlePointerMove(e);
                }
            });

            canvasElement.addEventListener('pointerup', (e) => {
                console.log('[FC3D] Pointerup on canvas at', e.clientX, e.clientY);

                // If we were drawing an arrow, finish it
                if (this.isDrawingArrow) {
                    this.handlePointerUp(e);
                    return;
                }

                // Detect click (short press without much movement)
                const elapsed = Date.now() - (this._pointerDownTime || 0);
                const moved = this._pointerDownPos ?
                    Math.hypot(e.clientX - this._pointerDownPos.x, e.clientY - this._pointerDownPos.y) : 999;

                if (elapsed < 300 && moved < 10) {
                    console.log('[FC3D] Detected as click! Calling onClick...');
                    this.onClick(e);
                } else {
                    console.log('[FC3D] Not a click (elapsed:', elapsed, 'ms, moved:', moved, 'px)');
                }
            });

            // Listen for Escape key to cancel drawing
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isDrawingArrow) {
                    this.cancelDrawingArrow();
                }
            });

            // Touch events for tablet support
            canvasElement.addEventListener('touchstart', (e) => {
                if (this.isEditing && e.touches.length === 1) {
                    // Convert touch to pointer-like event
                    const touch = e.touches[0];
                    this.handlePointerDown({
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                }
            }, { passive: true });

            canvasElement.addEventListener('touchmove', (e) => {
                if (this.isDrawingArrow && e.touches.length === 1) {
                    const touch = e.touches[0];
                    this.handlePointerMove({
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                }
            }, { passive: true });

            canvasElement.addEventListener('touchend', (e) => {
                if (this.isDrawingArrow && e.changedTouches.length === 1) {
                    const touch = e.changedTouches[0];
                    this.handlePointerUp({
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                }
            });

            // Also keep regular click handler as backup
            canvasElement.addEventListener('click', (e) => {
                // Don't process clicks if we just finished drawing
                if (this._justFinishedDrawing) {
                    this._justFinishedDrawing = false;
                    return;
                }
                console.log('[FC3D] Native click event fired!');
                this.onClick(e);
            });

            // Debug: Document-level listener to see if clicks are being captured elsewhere
            document.addEventListener('click', (e) => {
                const rect = canvasElement.getBoundingClientRect();
                const isInCanvas = e.clientX >= rect.left && e.clientX <= rect.right &&
                                   e.clientY >= rect.top && e.clientY <= rect.bottom;
                if (isInCanvas) {
                    console.log('[FC3D] Document click in canvas area, target:', e.target.tagName, e.target.className);
                }
            }, true); // capture phase

            canvasElement.addEventListener('mousemove', (e) => this.onMouseMove(e));
            window.addEventListener('resize', () => this.onResize(container));

            console.log('[FC3D] Event listeners attached successfully');

            // Debug: Log canvas position and size
            const canvasRect = canvasElement.getBoundingClientRect();
            console.log('[FC3D] Canvas rect:', canvasRect);
            console.log('[FC3D] Canvas is visible:', canvasRect.width > 0 && canvasRect.height > 0);

            // Store reference for later debugging
            this._canvasElement = canvasElement;
        },

        loadModel(path) {
            console.log('[FC3D] Loading model from:', path);

            // Check if GLTFLoader is available
            if (typeof THREE.GLTFLoader === 'undefined') {
                console.error('[FC3D] GLTFLoader not available!');
                this.error = 'GLTFLoader not loaded';
                this.loading = false;
                return;
            }

            const loader = new THREE.GLTFLoader();
            let lastLoggedPct = -1;

            loader.load(path,
                (gltf) => {
                    console.log('[FC3D] Model loaded successfully! Scene children:', gltf.scene.children.length);
                    if (isDestroyed) return;

                    faceModel = gltf.scene;

                    // Center and scale
                    const box = new THREE.Box3().setFromObject(faceModel);
                    const center = box.getCenter(new THREE.Vector3());
                    const size = box.getSize(new THREE.Vector3());
                    const maxDim = Math.max(size.x, size.y, size.z);

                    console.log('[FC3D] Model size:', size, 'maxDim:', maxDim);

                    faceModel.position.sub(center);
                    faceModel.scale.setScalar(1.8 / maxDim);

                    scene.add(faceModel);
                    console.log('[FC3D] Model added to scene');
                    this.loading = false;
                    console.log('[FC3D] Loading set to false, clicks should now work. isEditing:', this.isEditing);

                    // Initial markers
                    const markers = JSON.parse(JSON.stringify(config.markers || []));
                    this.renderMarkers(markers);
                },
                (progress) => {
                    // Log progress (handle missing Content-Length)
                    if (progress.total > 0) {
                        const pct = Math.round(progress.loaded / progress.total * 100);
                        if (pct !== lastLoggedPct && (pct % 10 === 0 || pct === 100)) {
                            console.log('[FC3D] Loading:', pct + '%', '(' + Math.round(progress.loaded/1024/1024) + 'MB)');
                            lastLoggedPct = pct;
                        }
                    } else if (progress.loaded > 0) {
                        // No total available, just log loaded bytes
                        const mb = Math.round(progress.loaded / 1024 / 1024);
                        if (mb % 10 === 0) {
                            console.log('[FC3D] Loading... ' + mb + 'MB loaded');
                        }
                    }
                },
                (err) => {
                    console.error('[FC3D] Model load error:', err);
                    console.error('[FC3D] Error details:', JSON.stringify(err, Object.getOwnPropertyNames(err)));
                    this.error = '{{ __('face_chart::face_chart.viewer.loading_error') }}';
                    this.loading = false;
                }
            );
        },

        renderMarkers(markers) {
            if (!faceModel || !scene) return;

            // Clear old markers and direction lines
            markerMeshes.forEach(m => scene.remove(m));
            markerMeshes = [];

            const box = new THREE.Box3().setFromObject(faceModel);
            const size = box.getSize(new THREE.Vector3());
            const center = box.getCenter(new THREE.Vector3());

            // Map marker types to icon files
            const typeToIcon = {
                'injection': 'injection',
                'syringe': 'injection',
                'laser': 'laser',
                'laser_spot': 'laser',
                'filler': 'filler',
                'filler_point': 'filler',
                'thread': 'thread',
                'thread_anchor': 'thread',
                'marking': 'marking',
            };

            markers.forEach(marker => {
                // Calculate base position
                const baseX = (marker.x || 0) * (size.x / 2) + center.x;
                const baseY = (marker.y || 0) * (size.y / 2) + center.y;
                const baseZ = (marker.z || 0) * (size.z / 2) + center.z;

                // Offset marker outward from face surface (push toward camera)
                const offsetAmount = 0.03;
                const x = baseX;
                const y = baseY;
                const z = baseZ + offsetAmount;

                const markerType = marker.type || 'injection';
                const iconType = typeToIcon[markerType] || typeToIcon[marker.icon] || 'injection';
                const iconPath = iconPaths[iconType] || iconPaths['default'];
                const spriteSize = 0.06 * (marker.size || 1);

                console.log('[FC3D] Marker', marker.id, 'using PNG icon:', iconPath);

                // Create sprite with PNG texture
                const createSprite = (texture) => {
                    const spriteMaterial = new THREE.SpriteMaterial({
                        map: texture,
                        transparent: true,
                        opacity: marker.isCurrent ? 1 : 0.7,
                        depthTest: true,
                        depthWrite: false,
                        sizeAttenuation: true
                    });

                    const sprite = new THREE.Sprite(spriteMaterial);
                    sprite.position.set(x, y, z);
                    sprite.scale.set(spriteSize, spriteSize, 1);

                    sprite.userData = {
                        id: marker.id,
                        type: marker.type,
                        product: marker.product,
                        dosage: marker.dosage,
                        date: marker.performedAt,
                        serviceName: marker.serviceName,
                        categoryName: marker.categoryName,
                        units: marker.units,
                        unitType: marker.unitType,
                        isMarker: true
                    };

                    scene.add(sprite);
                    markerMeshes.push(sprite);
                };

                // Load texture (use cache if available)
                if (textureCache[iconPath]) {
                    createSprite(textureCache[iconPath]);
                } else {
                    textureLoader.load(iconPath,
                        (texture) => {
                            textureCache[iconPath] = texture;
                            createSprite(texture);
                        },
                        undefined,
                        (err) => {
                            console.warn('[FC3D] Failed to load icon:', iconPath, 'using fallback sphere');
                            // Fallback to colored sphere if PNG fails
                            const fallbackGeo = new THREE.SphereGeometry(0.02, 12, 12);
                            const fallbackMat = new THREE.MeshPhongMaterial({
                                color: marker.color || '#ef4444',
                                emissive: marker.color || '#ef4444',
                                emissiveIntensity: 0.3
                            });
                            const fallback = new THREE.Mesh(fallbackGeo, fallbackMat);
                            fallback.position.set(x, y, z);
                            fallback.userData = {
                                id: marker.id,
                                type: marker.type,
                                product: marker.product,
                                dosage: marker.dosage,
                                date: marker.performedAt,
                                serviceName: marker.serviceName,
                                categoryName: marker.categoryName,
                                units: marker.units,
                                unitType: marker.unitType,
                                isMarker: true
                            };
                            scene.add(fallback);
                            markerMeshes.push(fallback);
                        }
                    );
                }

                // Draw 3D direction arrow if direction data exists (teal arrow like anatomical charts)
                if (marker.directionX !== null && marker.directionY !== null && marker.directionZ !== null) {
                    const arrowColor = 0x2D6B6B;
                    const arrowOpacity = marker.isCurrent ? 1 : 0.85;

                    // Direction vector - points in the direction the arrow should go
                    const dirX = parseFloat(marker.directionX) || 0;
                    const dirY = parseFloat(marker.directionY) || 0;
                    const dirZ = parseFloat(marker.directionZ) || 1;
                    const arrowDir = new THREE.Vector3(dirX, dirY, dirZ).normalize();

                    // Arrow dimensions - flexible based on stored depth
                    const depthMm = marker.depthMm || 5;  // Default 5mm if not set
                    const arrowLength = depthMm / 150;    // Scale mm to 3D units
                    const shaftRadius = 0.002;
                    const headRadius = Math.min(0.008, arrowLength * 0.25);
                    const headLength = Math.min(0.015, arrowLength * 0.35);
                    const shaftLength = Math.max(0.005, arrowLength - headLength);

                    const arrowMaterial = new THREE.MeshPhongMaterial({
                        color: arrowColor,
                        emissive: arrowColor,
                        emissiveIntensity: 0.3,
                        shininess: 80,
                        transparent: arrowOpacity < 1,
                        opacity: arrowOpacity,
                        side: THREE.DoubleSide
                    });

                    // Build arrow pointing in +Y direction, then rotate
                    const arrowGroup = new THREE.Group();

                    // Shaft (cylinder along Y axis)
                    const shaftGeo = new THREE.CylinderGeometry(shaftRadius, shaftRadius, shaftLength, 8);
                    const shaft = new THREE.Mesh(shaftGeo, arrowMaterial);
                    shaft.position.set(0, shaftLength / 2, 0);
                    arrowGroup.add(shaft);

                    // Arrowhead (cone at top of shaft)
                    const headGeo = new THREE.ConeGeometry(headRadius, headLength, 8);
                    const head = new THREE.Mesh(headGeo, arrowMaterial);
                    head.position.set(0, shaftLength + headLength / 2, 0);
                    arrowGroup.add(head);

                    // Position at marker (slightly offset from surface)
                    arrowGroup.position.set(x, y, z + 0.01);

                    // Rotate from +Y to arrow direction
                    const quaternion = new THREE.Quaternion();
                    quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), arrowDir);
                    arrowGroup.setRotationFromQuaternion(quaternion);

                    arrowGroup.userData = { isDirectionLine: true, markerId: marker.id };
                    scene.add(arrowGroup);
                    markerMeshes.push(arrowGroup);

                    // Small sphere at arrow base (injection point on skin)
                    const baseSphereGeo = new THREE.SphereGeometry(0.004, 8, 8);
                    const baseSphereMat = new THREE.MeshPhongMaterial({
                        color: arrowColor,
                        emissive: arrowColor,
                        emissiveIntensity: 0.4,
                        shininess: 100,
                        transparent: arrowOpacity < 1,
                        opacity: arrowOpacity
                    });
                    const baseSphere = new THREE.Mesh(baseSphereGeo, baseSphereMat);
                    baseSphere.position.set(x, y, z + 0.01);
                    baseSphere.userData = { isDirectionLine: true, markerId: marker.id };
                    scene.add(baseSphere);
                    markerMeshes.push(baseSphere);
                }
            });
        },

        onClick(e) {
            console.log('[FC3D] Click detected, isEditing:', this.isEditing);

            if (!faceModel || !renderer) {
                console.log('[FC3D] No faceModel or renderer');
                return;
            }

            const rect = renderer.domElement.getBoundingClientRect();
            mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

            raycaster.setFromCamera(mouse, camera);

            // Check markers first (filter for actual markers with IDs)
            // This works regardless of edit mode - users can always select markers to view details
            const markerHits = raycaster.intersectObjects(markerMeshes, true)
                .filter(hit => hit.object.userData && hit.object.userData.isMarker && hit.object.userData.id);

            console.log('[FC3D] Marker hits:', markerHits.length, 'total marker meshes:', markerMeshes.length);

            if (markerHits.length > 0) {
                const markerData = markerHits[0].object.userData;
                console.log('[FC3D] Marker clicked:', markerData.id, markerData);
                // Open edit modal with marker data
                this.openEditMarkerModal(markerData);
                return;
            }

            // Check face for new marker - only in edit mode
            if (this.isEditing) {
                const faceHits = raycaster.intersectObject(faceModel, true);
                console.log('[FC3D] Face hits:', faceHits.length);

                if (faceHits.length > 0) {
                    const p = faceHits[0].point;
                    const box = new THREE.Box3().setFromObject(faceModel);
                    const size = box.getSize(new THREE.Vector3());
                    const center = box.getCenter(new THREE.Vector3());

                    const x = (p.x - center.x) / (size.x / 2);
                    const y = (p.y - center.y) / (size.y / 2);
                    const z = (p.z - center.z) / (size.z / 2);
                    const region = this.detectRegion(y, x);

                    console.log('[FC3D] Opening modal for region:', region, 'at', x, y, z);
                    this.openMarkerModal(x, y, z, region);
                }
            } else {
                // View mode - clicking on empty space does nothing
                console.log('[FC3D] View mode - click on empty space');
            }
        },

        // Open the add marker modal (new marker)
        openMarkerModal(x, y, z, region) {
            console.log('[FC3D] openMarkerModal called (new), region:', region);
            this.isEditingMarker = false;
            this.editingMarkerId = null;
            this.pendingMarker = {
                x: x,
                y: y,
                z: z,
                region: region,
                regionLabel: regionLabels[region] || region || 'Unknown',
                product_name: '',
                units: '',
                unit_type: 'units',
                direction_preset: '',
                direction_x: null,
                direction_y: null,
                direction_z: null,
                depth_mm: '',
                notes: '',
                is_editable: true,
                performed_at: '',
                performed_by_name: '',
            };
            this.showMarkerModal = true;
        },

        // Open the edit marker modal (existing marker)
        openEditMarkerModal(markerData) {
            console.log('[FC3D] openEditMarkerModal called, id:', markerData.id);
            // Request full marker data from Livewire
            this.$wire.getMarkerForEdit(markerData.id);
        },

        // Populate the edit modal with marker data from Livewire
        populateEditModal(marker) {
            console.log('[FC3D] populateEditModal called:', marker);
            console.log('[FC3D] Marker is_editable:', marker.is_editable, 'direction:', marker.direction_x, marker.direction_y, marker.direction_z);
            this.isEditingMarker = true;
            this.editingMarkerId = marker.id;

            // Detect direction preset from values
            let directionPreset = '';
            if (marker.direction_x !== null && marker.direction_y !== null && marker.direction_z !== null) {
                const dx = parseFloat(marker.direction_x) || 0;
                const dy = parseFloat(marker.direction_y) || 0;
                const dz = parseFloat(marker.direction_z) || 0;

                // Check against known presets (with tolerance)
                if (Math.abs(dx) < 0.1 && Math.abs(dy) < 0.1 && Math.abs(dz + 1) < 0.1) {
                    directionPreset = 'perpendicular';
                } else if (Math.abs(dx) < 0.1 && Math.abs(dy + 0.707) < 0.1 && Math.abs(dz + 0.707) < 0.1) {
                    directionPreset = 'angled_down';
                } else if (Math.abs(dx) < 0.1 && Math.abs(dy - 0.707) < 0.1 && Math.abs(dz + 0.707) < 0.1) {
                    directionPreset = 'angled_up';
                } else {
                    directionPreset = 'custom';
                }
                console.log('[FC3D] Detected direction preset:', directionPreset, 'from', dx, dy, dz);
            }

            this.pendingMarker = {
                x: marker.x,
                y: marker.y,
                z: marker.z,
                region: marker.face_region || '',
                regionLabel: regionLabels[marker.face_region] || marker.face_region || 'Unknown',
                product_name: marker.product_name || '',
                units: marker.units || '',
                unit_type: marker.unit_type || 'units',
                direction_preset: directionPreset,
                direction_x: marker.direction_x,
                direction_y: marker.direction_y,
                direction_z: marker.direction_z,
                depth_mm: marker.depth_mm || '',
                notes: marker.notes || '',
                is_editable: marker.is_editable,
                performed_at: marker.performed_at || '',
                performed_by_name: marker.performed_by_name || '',
                marker_type: marker.marker_type || 'injection',
                color: marker.color || '#3B82F6',
                // Debug fields
                _debug_marker_appt: marker._debug_marker_appt,
                _debug_current_appt: marker._debug_current_appt,
            };
            console.log('[FC3D] pendingMarker set, is_editable:', this.pendingMarker.is_editable);
            this.showMarkerModal = true;
        },

        // Apply direction preset (legacy - kept for modal dropdown fallback)
        applyDirectionPreset() {
            const preset = this.pendingMarker.direction_preset;
            switch(preset) {
                case 'perpendicular':
                    this.pendingMarker.direction_x = 0;
                    this.pendingMarker.direction_y = 0;
                    this.pendingMarker.direction_z = -1;
                    break;
                case 'angled_down':
                    this.pendingMarker.direction_x = 0;
                    this.pendingMarker.direction_y = -0.707;
                    this.pendingMarker.direction_z = -0.707;
                    break;
                case 'angled_up':
                    this.pendingMarker.direction_x = 0;
                    this.pendingMarker.direction_y = 0.707;
                    this.pendingMarker.direction_z = -0.707;
                    break;
                case 'horizontal':
                    this.pendingMarker.direction_x = 0;
                    this.pendingMarker.direction_y = 0;
                    this.pendingMarker.direction_z = -1;
                    break;
                case 'custom':
                    break;
                case '':
                default:
                    this.pendingMarker.direction_x = null;
                    this.pendingMarker.direction_y = null;
                    this.pendingMarker.direction_z = null;
            }
        },

        // ============================================
        // DRAG-TO-DRAW ARROW FUNCTIONS
        // ============================================

        // Create a preview arrow during drag
        createPreviewArrow(startPoint, endPoint) {
            // Remove any existing preview
            this.removePreviewArrow();

            if (!startPoint || !endPoint) return;

            // Calculate drag vector - arrow points in drag direction
            const dragVector = new THREE.Vector3().subVectors(endPoint, startPoint);
            const dragDistance = dragVector.length();

            // Minimum length check
            if (dragDistance < 0.01) return;

            // Arrow direction is the SURFACE NORMAL (perpendicular to face)
            const arrowDir = drawingSurfaceNormal ? drawingSurfaceNormal.clone() : new THREE.Vector3(0, 0, 1);

            // Arrow length - flexible based on drag distance
            const arrowLength = Math.max(0.02, dragDistance * 0.8);  // Scale with drag
            const shaftRadius = 0.002;
            const headRadius = Math.min(0.008, arrowLength * 0.25);
            const headLength = Math.min(0.015, arrowLength * 0.35);
            const shaftLength = Math.max(0.005, arrowLength - headLength);

            // Semi-transparent teal material for preview
            const previewMaterial = new THREE.MeshPhongMaterial({
                color: 0x2D6B6B,
                emissive: 0x2D6B6B,
                emissiveIntensity: 0.3,
                shininess: 80,
                transparent: true,
                opacity: 0.7,
                side: THREE.DoubleSide
            });

            // Build arrow pointing in +Y direction, then rotate
            previewArrowGroup = new THREE.Group();

            // Shaft (cylinder along Y axis)
            const shaftGeo = new THREE.CylinderGeometry(shaftRadius, shaftRadius, shaftLength, 8);
            const shaft = new THREE.Mesh(shaftGeo, previewMaterial);
            shaft.position.set(0, shaftLength / 2, 0);
            previewArrowGroup.add(shaft);

            // Arrowhead (cone at top of shaft)
            const headGeo = new THREE.ConeGeometry(headRadius, headLength, 8);
            const head = new THREE.Mesh(headGeo, previewMaterial);
            head.position.set(0, shaftLength + headLength / 2, 0);
            previewArrowGroup.add(head);

            // Position at marker (slightly offset from surface)
            previewArrowGroup.position.copy(startPoint);
            previewArrowGroup.position.z += 0.01;

            // Rotate from +Y to arrow direction
            const quaternion = new THREE.Quaternion();
            quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), arrowDir);
            previewArrowGroup.setRotationFromQuaternion(quaternion);

            // Small sphere at arrow base
            const baseSphereGeo = new THREE.SphereGeometry(0.004, 8, 8);
            const baseSphereMat = new THREE.MeshPhongMaterial({
                color: 0x2D6B6B,
                emissive: 0x2D6B6B,
                emissiveIntensity: 0.5,
                transparent: true,
                opacity: 0.8
            });
            const baseSphere = new THREE.Mesh(baseSphereGeo, baseSphereMat);
            baseSphere.position.set(0, 0, 0);
            previewArrowGroup.add(baseSphere);

            previewArrowGroup.userData = { isPreviewArrow: true };
            scene.add(previewArrowGroup);
        },

        // Update preview arrow during drag
        updatePreviewArrow(endPoint) {
            if (!drawStartPoint || !endPoint) return;

            // Recreate the arrow with new endpoint
            this.createPreviewArrow(drawStartPoint, endPoint);
        },

        // Remove preview arrow from scene
        removePreviewArrow() {
            if (previewArrowGroup && scene) {
                scene.remove(previewArrowGroup);
                // Dispose of geometries and materials
                previewArrowGroup.traverse((child) => {
                    if (child.geometry) child.geometry.dispose();
                    if (child.material) child.material.dispose();
                });
                previewArrowGroup = null;
            }
        },

        // Start drawing arrow on marker click
        startDrawingArrow(markerId, markerPosition, surfaceNormal) {
            console.log('[FC3D] Starting arrow draw for marker:', markerId, 'normal:', surfaceNormal);
            this.isDrawingArrow = true;
            this.drawingMarkerId = markerId;
            drawStartPoint = markerPosition.clone();
            drawingMarkerPosition = markerPosition.clone();
            drawingSurfaceNormal = surfaceNormal ? surfaceNormal.clone() : new THREE.Vector3(0, 0, 1);

            // Change cursor to grabbing while drawing
            if (renderer && renderer.domElement) {
                renderer.domElement.style.cursor = 'grabbing';
            }
        },

        // Finish drawing arrow and save direction
        finishDrawingArrow(endPoint) {
            if (!this.isDrawingArrow || !this.drawingMarkerId || !drawStartPoint) {
                this.cancelDrawingArrow();
                return;
            }

            // Calculate drag distance for arrow length
            const dragVector = new THREE.Vector3().subVectors(endPoint, drawStartPoint);
            const dragDistance = dragVector.length();

            // Minimum drag distance check (prevents accidental clicks)
            const MIN_DRAG_DISTANCE = 0.015;
            if (dragDistance < MIN_DRAG_DISTANCE) {
                console.log('[FC3D] Drag too short, canceling');
                this.cancelDrawingArrow();
                return;
            }

            // Direction is the SURFACE NORMAL (perpendicular to face surface)
            // This makes arrows stick out properly from curved surfaces
            const direction = drawingSurfaceNormal ? drawingSurfaceNormal.clone() : new THREE.Vector3(0, 0, 1);

            // Depth based on drag distance (flexible size)
            const depth = dragDistance * 80;  // Scale drag to mm

            console.log('[FC3D] Arrow - normal direction:', direction, 'depth:', depth.toFixed(1), 'mm');

            // Save direction via Livewire
            this.$wire.onSetDirection(
                this.drawingMarkerId,
                direction.x,
                direction.y,
                direction.z,
                depth
            );

            // Clean up
            this.removePreviewArrow();
            this.resetDrawingState();
        },

        // Cancel arrow drawing (e.g., on Escape)
        cancelDrawingArrow() {
            console.log('[FC3D] Canceling arrow draw');
            this.removePreviewArrow();
            this.resetDrawingState();
        },

        // Reset drawing state
        resetDrawingState() {
            this.isDrawingArrow = false;
            this.drawingMarkerId = null;
            drawStartPoint = null;
            drawCurrentPoint = null;
            drawingMarkerPosition = null;
            drawingSurfaceNormal = null;

            // Reset cursor
            if (renderer && renderer.domElement) {
                renderer.domElement.style.cursor = 'crosshair';
            }
        },

        // Project mouse position to 3D plane at marker's depth
        projectMouseToPlane(mouseEvent, referencePoint) {
            if (!renderer || !camera || !referencePoint) return null;

            const rect = renderer.domElement.getBoundingClientRect();
            const mouseNDC = new THREE.Vector2(
                ((mouseEvent.clientX - rect.left) / rect.width) * 2 - 1,
                -((mouseEvent.clientY - rect.top) / rect.height) * 2 + 1
            );

            // Simple: plane perpendicular to camera at reference point
            const cameraDirection = camera.getWorldDirection(new THREE.Vector3());
            const planeNormal = cameraDirection.clone().negate();
            const plane = new THREE.Plane();
            plane.setFromNormalAndCoplanarPoint(planeNormal, referencePoint);

            // Cast ray from mouse position
            const ray = new THREE.Raycaster();
            ray.setFromCamera(mouseNDC, camera);

            // Find intersection with plane
            const intersection = new THREE.Vector3();
            const hit = ray.ray.intersectPlane(plane, intersection);

            return hit ? intersection : null;
        },

        // Handle pointer down - check if starting arrow draw on marker
        handlePointerDown(e) {
            if (!this.isEditing || !faceModel || !renderer) return;

            const rect = renderer.domElement.getBoundingClientRect();
            mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

            raycaster.setFromCamera(mouse, camera);

            // Check if clicking on a marker
            const markerHits = raycaster.intersectObjects(markerMeshes, true)
                .filter(hit => hit.object.userData && hit.object.userData.isMarker && hit.object.userData.id);

            if (markerHits.length > 0) {
                const marker = markerHits[0].object;
                const markerId = marker.userData.id;
                const markerPosition = marker.position.clone();

                // Get face surface normal at this point by raycasting to the face model
                let surfaceNormal = null;
                const faceHits = raycaster.intersectObject(faceModel, true);
                if (faceHits.length > 0 && faceHits[0].face) {
                    // Get the face normal and transform to world space
                    surfaceNormal = faceHits[0].face.normal.clone();
                    surfaceNormal.transformDirection(faceHits[0].object.matrixWorld);
                    console.log('[FC3D] Got surface normal from face:', surfaceNormal);
                }

                // Fallback: estimate normal as pointing outward from face center
                if (!surfaceNormal) {
                    // Assume face center is around (0, 0, 0), normal points outward
                    surfaceNormal = markerPosition.clone().normalize();
                    console.log('[FC3D] Estimated surface normal:', surfaceNormal);
                }

                // Start arrow drawing mode with surface normal
                this.startDrawingArrow(markerId, markerPosition, surfaceNormal);

                // Prevent orbit controls from interfering
                if (controls) {
                    controls.enabled = false;
                }
            }
        },

        // Handle pointer move during arrow drawing
        handlePointerMove(e) {
            if (!this.isDrawingArrow || !drawStartPoint) return;

            // Project mouse to 3D plane
            const endPoint = this.projectMouseToPlane(e, drawStartPoint);
            if (endPoint) {
                drawCurrentPoint = endPoint;
                this.updatePreviewArrow(endPoint);
            }
        },

        // Handle pointer up - finish arrow drawing
        handlePointerUp(e) {
            if (!this.isDrawingArrow) return;

            // Re-enable orbit controls
            if (controls) {
                controls.enabled = true;
            }

            // Project final mouse position
            const endPoint = this.projectMouseToPlane(e, drawStartPoint);
            if (endPoint) {
                this.finishDrawingArrow(endPoint);
            } else {
                this.cancelDrawingArrow();
            }

            // Mark that we just finished drawing to prevent click handler
            this._justFinishedDrawing = true;
        },

        // Save marker via Livewire (handles both add and edit)
        saveMarker() {
            const formData = {
                product_name: this.pendingMarker.product_name,
                units: this.pendingMarker.units ? parseFloat(this.pendingMarker.units) : null,
                unit_type: this.pendingMarker.unit_type,
                direction_x: this.pendingMarker.direction_x,
                direction_y: this.pendingMarker.direction_y,
                direction_z: this.pendingMarker.direction_z,
                depth_mm: this.pendingMarker.depth_mm ? parseFloat(this.pendingMarker.depth_mm) : null,
                notes: this.pendingMarker.notes,
            };

            if (this.isEditingMarker && this.editingMarkerId) {
                // Update existing marker
                console.log('[FC3D] Updating marker:', this.editingMarkerId, formData);
                this.$wire.updateMarkerFromModal(this.editingMarkerId, formData);
            } else {
                // Create new marker
                console.log('[FC3D] Creating new marker at:', this.pendingMarker.x, this.pendingMarker.y, this.pendingMarker.z);
                this.$wire.onFaceClicked(
                    this.pendingMarker.x,
                    this.pendingMarker.y,
                    this.pendingMarker.z,
                    this.pendingMarker.region,
                    formData
                );
            }
            this.closeModal();
        },

        // Delete marker from modal
        deleteMarkerFromModal() {
            console.log('[FC3D] deleteMarkerFromModal called, isEditingMarker:', this.isEditingMarker,
                        'editingMarkerId:', this.editingMarkerId,
                        'is_editable:', this.pendingMarker.is_editable);

            if (this.isEditingMarker && this.editingMarkerId && this.pendingMarker.is_editable) {
                if (confirm('{{ __('face_chart::face_chart.confirm.delete_marker') }}')) {
                    console.log('[FC3D] Confirmed - Deleting marker:', this.editingMarkerId);
                    this.$wire.deleteMarkerFromModal(this.editingMarkerId);
                    this.closeModal();
                }
            } else {
                console.log('[FC3D] Cannot delete - conditions not met');
            }
        },

        // Close modal and reset state
        closeModal() {
            this.showMarkerModal = false;
            this.isEditingMarker = false;
            this.editingMarkerId = null;
        },

        // Cancel and close modal (alias for closeModal)
        cancelMarker() {
            this.closeModal();
        },

        onMouseMove(e) {
            if (!renderer || !raycaster) return;

            const rect = renderer.domElement.getBoundingClientRect();
            mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

            raycaster.setFromCamera(mouse, camera);
            const hits = raycaster.intersectObjects(markerMeshes, true)
                .filter(hit => hit.object.userData && hit.object.userData.isMarker && hit.object.userData.id);

            if (hits.length > 0) {
                const d = hits[0].object.userData;
                this.hoveredMarker = {
                    type: d.type,
                    product: d.product,
                    dosage: d.dosage,
                    date: d.date,
                    serviceName: d.serviceName,
                    categoryName: d.categoryName,
                    units: d.units,
                    unitType: d.unitType
                };
                this.tooltipStyle = `left:${e.clientX - rect.left + 10}px;top:${e.clientY - rect.top + 10}px`;

                // Change cursor to indicate draggable in edit mode
                if (this.isEditing && renderer.domElement) {
                    renderer.domElement.style.cursor = 'grab';
                }
            } else {
                this.hoveredMarker = null;

                // Reset cursor
                if (renderer.domElement) {
                    renderer.domElement.style.cursor = this.isEditing ? 'crosshair' : 'default';
                }
            }
        },

        detectRegion(y, x) {
            if (y > 0.6) return 'forehead';
            if (y > 0.4 && Math.abs(x) < 0.15) return 'glabella';
            if (y > 0.2 && Math.abs(x) > 0.3) return 'crow_feet';
            if (y > 0 && Math.abs(x) < 0.25) return 'nose';
            if (y > -0.2) return 'cheeks';
            if (y > -0.4) return 'nasolabial';
            if (y > -0.6) return 'chin';
            return 'jawline';
        },

        animate() {
            if (isDestroyed) return;
            animationId = requestAnimationFrame(() => this.animate());
            if (controls) controls.update();
            if (renderer && scene && camera) renderer.render(scene, camera);
        },

        onResize(container) {
            if (!camera || !renderer || !container) return;
            const w = container.clientWidth;
            const h = container.clientHeight;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        },

        resetCamera() {
            if (camera && controls) {
                camera.position.set(0, 0, 2.5);
                controls.reset();
            }
        },

        zoomIn() {
            if (camera) camera.position.z = Math.max(1, camera.position.z * 0.8);
        },

        zoomOut() {
            if (camera) camera.position.z = Math.min(5, camera.position.z * 1.2);
        },

        destroy() {
            isDestroyed = true;
            if (animationId) cancelAnimationFrame(animationId);
            if (renderer) {
                renderer.dispose();
                renderer.domElement?.remove();
            }
            scene = camera = renderer = controls = faceModel = null;
            markerMeshes = [];
        }
    };
});
</script>
@endscript
