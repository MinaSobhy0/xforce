<div class="flex flex-col gap-4" wire:ignore.self
     x-data="faceChart2DComponent()"
     x-init="startInitCheck()"
     @tab-switched-to-2d.window="console.log('🔔 Tab switched to 2D event received'); triggerInit()"
     @resize.window="if (fabricCanvas) fabricCanvas.renderAll()">

    {{-- Filters Bar (Top) --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ __('face_chart::face_chart.filters.date_from') }}
                </label>
                <input
                    type="date"
                    wire:model.defer="filterDateFrom"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm"
                >
            </div>

            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ __('face_chart::face_chart.filters.date_to') }}
                </label>
                <input
                    type="date"
                    wire:model.defer="filterDateTo"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm"
                >
            </div>

            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ __('face_chart::face_chart.filters.region') }}
                </label>
                <select
                    wire:model.defer="filterRegion"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm"
                >
                    <option value="">{{ __('face_chart::face_chart.filters.all_regions') }}</option>
                    @foreach($regions as $region)
                        <option value="{{ $region }}">
                            {{ __("face_chart::face_chart.regions.{$region}") }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ __('face_chart::face_chart.filters.type') }}
                </label>
                <select
                    wire:model.defer="filterType"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm"
                >
                    <option value="">{{ __('face_chart::face_chart.filters.all_types') }}</option>
                    @foreach($markerTypes as $type)
                        <option value="{{ $type }}">
                            {{ __("face_chart::face_chart.marker_types.{$type}") }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button
                    wire:click="applyFilters"
                    class="bg-primary-500 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-600 transition whitespace-nowrap"
                >
                    {{ __('face_chart::face_chart.filters.apply') }}
                </button>
                <button
                    wire:click="clearFilters"
                    class="bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md px-4 py-2 text-sm hover:bg-gray-400 dark:hover:bg-gray-500 transition whitespace-nowrap"
                >
                    {{ __('face_chart::face_chart.filters.clear') }}
                </button>
                @if($isEditing)
                <button
                    wire:click="clearAllMarkers"
                    wire:confirm="Are you sure you want to delete all markers on this face chart?"
                    class="bg-red-500 text-white rounded-md px-4 py-2 text-sm hover:bg-red-600 transition whitespace-nowrap"
                >
                    Clear All Markers
                </button>
                @endif
            </div>
        </div>

        {{-- Statistics Row --}}
        <div class="flex flex-wrap gap-6 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 text-sm">
            <div class="flex gap-2">
                <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.statistics.total_markers') }}:</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $stats['total_markers'] ?? 0 }}</span>
            </div>
            <div class="flex gap-2">
                <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.statistics.total_units') }}:</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_units'] ?? 0, 2) }}</span>
            </div>
            <div class="flex gap-2">
                <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.statistics.appointments') }}:</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $stats['appointments_count'] ?? 0 }}</span>
            </div>
        </div>
    </div>

    {{-- View Tabs (Left/Front/Right) - Centered Pill Design --}}
    <div class="flex justify-center">
        <div class="inline-flex bg-gray-100 dark:bg-gray-800 rounded-full p-1 shadow-sm border border-gray-200 dark:border-gray-700">
            <button
                @click="switchView('left')"
                :class="currentView === 'left' ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-md' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'"
                class="flex items-center gap-2 px-6 py-2.5 rounded-full text-sm font-semibold transition-all duration-200"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ __('face_chart::face_chart.views.left') ?: 'Left' }}
            </button>
            <button
                @click="switchView('front')"
                :class="currentView === 'front' ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-md' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'"
                class="flex items-center gap-2 px-6 py-2.5 rounded-full text-sm font-semibold transition-all duration-200"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                {{ __('face_chart::face_chart.views.front') ?: 'Front' }}
            </button>
            <button
                @click="switchView('right')"
                :class="currentView === 'right' ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-md' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'"
                class="flex items-center gap-2 px-6 py-2.5 rounded-full text-sm font-semibold transition-all duration-200"
            >
                {{ __('face_chart::face_chart.views.right') ?: 'Right' }}
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Canvas Area (Full width) --}}
    <div class="w-full relative rounded-lg overflow-hidden" style="min-height: 700px; touch-action: pan-x pan-y;"
         @wheel.prevent="handleWheelZoom($event)"
         @touchstart="handleTouchStart($event)"
         @touchmove.prevent="handleTouchMove($event)"
         @touchend="handleTouchEnd($event)">
        <div class="w-full h-full flex items-center justify-center" style="overflow: auto;">
            <div id="canvasContainer" wire:ignore
                 @mousedown="startPan($event)"
                 @mousemove="doPan($event)"
                 @mouseup="endPan()"
                 @mouseleave="endPan()"
                 @touchstart="startPanTouch($event)"
                 @touchmove="doPanTouch($event)"
                 @touchend="endPan()"
                 :style="`position: relative; display: inline-block; transform: translate(${panX}px, ${panY}px) scale(${zoomLevel}); transform-origin: top left; transition: ${isPanning ? 'none' : 'transform 0.1s'}; cursor: ${panMode ? (isPanning ? 'grabbing' : 'grab') : 'default'};`">
                <!-- Background Image (HTML layer behind canvas) - will be sized by JS -->
                <img
                    id="faceBackgroundImage"
                    :src="viewImages[currentView]"
                    :style="`position: absolute; top: 0; left: 0; object-fit: fill; pointer-events: none; z-index: 1; display: block; visibility: ${imageLoading ? 'hidden' : 'visible'};`"
                    alt="Face diagram"
                />

                <!-- Transparent Canvas layer on top -->
                <canvas
                    id="faceChart2DCanvas"
                    style="position: relative; display: block; z-index: 2; background: transparent;">
                </canvas>
            </div>
        </div>

        {{-- Zoom Controls --}}
        <div class="absolute bottom-4 right-4 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-2 flex flex-col gap-1" style="z-index: 50;">
            <button
                @click="zoomIn()"
                class="w-10 h-10 rounded flex items-center justify-center bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:opacity-80 transition"
                title="Zoom In"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m-3-3h6" />
                </svg>
            </button>
            <div class="text-xs text-center text-gray-600 dark:text-gray-400 py-1" x-text="Math.round(zoomLevel * 100) + '%'"></div>
            <button
                @click="zoomOut()"
                class="w-10 h-10 rounded flex items-center justify-center bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:opacity-80 transition"
                title="Zoom Out"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM7 10h6" />
                </svg>
            </button>
            <button
                @click="panMode = !panMode"
                :class="panMode ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                class="w-10 h-10 rounded flex items-center justify-center hover:opacity-80 transition"
                title="Pan Mode (drag to move)"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 004.5 9.75v7.5a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25v-7.5a2.25 2.25 0 00-2.25-2.25h-.75m0-3l-3-3m0 0l-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 012.25 2.25v7.5a2.25 2.25 0 01-2.25 2.25h-7.5a2.25 2.25 0 01-2.25-2.25v-.75" />
                </svg>
            </button>
            <button
                @click="resetZoom()"
                class="w-10 h-10 rounded flex items-center justify-center bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:opacity-80 transition"
                title="Reset Zoom & Position"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
        </div>

        {{-- Toolbar (if editing) --}}
        @if($isEditing)
        <div class="absolute top-4 left-4 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-2 space-y-2" style="z-index: 50;">
            {{-- Drawing Tools --}}
            <button
                @click="selectTool('marker')"
                :class="selectedTool === 'marker' ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                class="w-10 h-10 rounded flex items-center justify-center hover:opacity-80 transition"
                title="{{ __('face_chart::face_chart.2d.tools.marker') }}"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </button>

            <button
                @click="selectTool('text')"
                :class="selectedTool === 'text' ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                class="w-10 h-10 rounded flex items-center justify-center hover:opacity-80 transition"
                title="{{ __('face_chart::face_chart.2d.tools.text') }}"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>

            <button
                @click="selectTool('arrow')"
                :class="selectedTool === 'arrow' ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                class="w-10 h-10 rounded flex items-center justify-center hover:opacity-80 transition"
                title="{{ __('face_chart::face_chart.2d.tools.arrow') }}"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>

            <button
                @click="selectTool('pen')"
                :class="selectedTool === 'pen' ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                class="w-10 h-10 rounded flex items-center justify-center hover:opacity-80 transition"
                title="{{ __('face_chart::face_chart.2d.tools.pen') }}"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                </svg>
            </button>

            <button
                @click="selectTool('select')"
                :class="selectedTool === 'select' ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                class="w-10 h-10 rounded flex items-center justify-center hover:opacity-80 transition"
                title="{{ __('face_chart::face_chart.2d.tools.select') }}"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                </svg>
            </button>

            <button
                @click="panMode = !panMode; if (panMode && fabricCanvas) { fabricCanvas.isDrawingMode = false; fabricCanvas.selection = false; }"
                :class="panMode ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                class="w-10 h-10 rounded flex items-center justify-center hover:opacity-80 transition"
                title="Pan (drag to move)"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 004.5 9.75v7.5a2.25 2.25 0 002.25 2.25h7.5a2.25 2.25 0 002.25-2.25v-7.5a2.25 2.25 0 00-2.25-2.25h-.75m0-3l-3-3m0 0l-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 012.25 2.25v7.5a2.25 2.25 0 01-2.25 2.25h-7.5a2.25 2.25 0 01-2.25-2.25v-.75" />
                </svg>
            </button>

            <button
                @click="selectTool('eraser')"
                :style="selectedTool === 'eraser' ? 'background-color: #f97316; color: white;' : ''"
                :class="selectedTool !== 'eraser' ? 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300' : ''"
                class="w-10 h-10 rounded flex items-center justify-center hover:opacity-80 transition"
                title="{{ __('face_chart::face_chart.2d.tools.eraser') }}"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>

            <div class="border-t border-gray-300 dark:border-gray-600 my-2"></div>

            {{-- Color Picker --}}
            <div class="flex flex-col items-center space-y-1">
                <div class="grid grid-cols-2 gap-1">
                    <button
                        @click="drawingColor = '#FF0000'"
                        :class="drawingColor === '#FF0000' ? 'ring-2 ring-primary-500' : ''"
                        class="w-4 h-4 rounded-full bg-red-500 hover:scale-110 transition"
                        title="Red"
                    ></button>
                    <button
                        @click="drawingColor = '#0000FF'"
                        :class="drawingColor === '#0000FF' ? 'ring-2 ring-primary-500' : ''"
                        class="w-4 h-4 rounded-full bg-blue-500 hover:scale-110 transition"
                        title="Blue"
                    ></button>
                    <button
                        @click="drawingColor = '#00FF00'"
                        :class="drawingColor === '#00FF00' ? 'ring-2 ring-primary-500' : ''"
                        class="w-4 h-4 rounded-full bg-green-500 hover:scale-110 transition"
                        title="Green"
                    ></button>
                    <button
                        @click="drawingColor = '#000000'"
                        :class="drawingColor === '#000000' ? 'ring-2 ring-primary-500' : ''"
                        class="w-4 h-4 rounded-full bg-black hover:scale-110 transition"
                        title="Black"
                    ></button>
                </div>
                <input
                    type="color"
                    x-model="drawingColor"
                    class="w-10 h-6 rounded cursor-pointer border-0"
                    title="{{ __('face_chart::face_chart.2d.tools.color') }}"
                />
            </div>

            <div class="border-t border-gray-300 dark:border-gray-600 my-2"></div>

            {{-- Stroke Size --}}
            <div class="flex flex-col items-center space-y-1">
                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="strokeWidth + 'px'"></span>
                <input
                    type="range"
                    x-model="strokeWidth"
                    min="1"
                    max="10"
                    class="w-10 h-2 cursor-pointer"
                    style="writing-mode: bt-lr; -webkit-appearance: slider-vertical;"
                    title="{{ __('face_chart::face_chart.2d.tools.size') }}"
                />
            </div>

            <div class="border-t border-gray-300 dark:border-gray-600 my-2"></div>

            {{-- Clear All --}}
            <button
                @click="if (confirm('{{ __('face_chart::face_chart.2d.confirm_clear') }}')) clearCanvas()"
                class="w-10 h-10 rounded flex items-center justify-center bg-red-500 text-white hover:bg-red-600 transition"
                title="{{ __('face_chart::face_chart.2d.tools.clear') }}"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </div>
        @endif

        {{-- Info Badge --}}
        <div class="absolute bottom-4 left-4 bg-white dark:bg-gray-800 rounded-lg shadow px-3 py-2 text-sm">
            <span class="text-gray-600 dark:text-gray-400">
                {{ __('face_chart::face_chart.2d.marker_count', ['count' => count($markers)]) }}
            </span>
        </div>
    </div>

    {{-- Marker Wizard Modal --}}
    @if($showMarkerWizard)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center" style="z-index: 100;" wire:key="marker-wizard-modal">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Add Injection Point</h3>
                <button wire:click="cancelMarkerWizard" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Consumable Product <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="wizardProductId"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    >
                        <option value="">-- Select a product --</option>
                        @foreach($availableConsumables as $product)
                            <option value="{{ $product['id'] }}">
                                {{ $product['name'] }} ({{ $product['price'] }})
                            </option>
                        @endforeach
                    </select>
                    @error('wizardProductId')
                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Quantity <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        wire:model="wizardQty"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Notes
                    </label>
                    <textarea
                        wire:model="wizardNotes"
                        rows="2"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        placeholder="Optional notes about this injection point"
                    ></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button
                    wire:click="cancelMarkerWizard"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 transition"
                >
                    Cancel
                </button>
                <button
                    wire:click="confirmMarkerWizard"
                    class="px-4 py-2 bg-primary-500 text-white rounded-md hover:bg-primary-600 transition"
                >
                    Add Marker
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Marker List (Below canvas) --}}
    <div class="w-full">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">
                {{ __('face_chart::face_chart.2d.markers_list') }}
            </h3>

            @if(empty($markers))
                <p class="text-gray-500 dark:text-gray-400 text-sm text-center py-4">
                    {{ __('face_chart::face_chart.2d.no_markers') }}
                </p>
            @else
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach($markers as $marker)
                        <div class="border border-gray-200 dark:border-gray-700 rounded p-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $marker['product'] ?? __('face_chart::face_chart.2d.unnamed_marker') }}
                                    </div>
                                    @if($marker['annotationText'])
                                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            {{ Str::limit($marker['annotationText'], 50) }}
                                        </div>
                                    @endif
                                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        {{ $marker['region'] ? __("face_chart::face_chart.regions.{$marker['region']}") : '' }}
                                        @if($marker['dosage'])
                                            · {{ $marker['dosage'] }}
                                        @endif
                                    </div>
                                </div>
                                @if($marker['isEditable'])
                                    <button
                                        wire:click="onMarkerDelete({{ $marker['id'] }})"
                                        class="text-red-500 hover:text-red-700 dark:hover:text-red-400"
                                        onclick="return confirm('{{ __('face_chart::face_chart.2d.confirm_delete') }}')"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

@assets
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
@endassets

@script
<script>
Alpine.data('faceChart2DComponent', () => ({
    fabricCanvas: null,
    backgroundImage: null, // Store reference to background
    initialized: false,
    selectedTool: 'marker', // marker, text, arrow, pen, select, eraser
    isDrawingArrow: false,
    arrowStartPoint: null,
    previewArrow: null, // Live preview arrow during drag
    drawingColor: '#FF0000', // Default red
    strokeWidth: 3, // Default stroke width
    zoomLevel: 1, // Current zoom level (1 = 100%)
    minZoom: 0.5,
    maxZoom: 3,
    zoomStep: 0.2,
    panX: 0,
    panY: 0,
    panMode: false,
    isPanning: false,
    panStartX: 0,
    panStartY: 0,
    panOriginX: 0,
    panOriginY: 0,

    startPan(event) {
        if (!this.panMode) return;
        event.preventDefault();
        event.stopPropagation();
        this.isPanning = true;
        this.panStartX = event.clientX;
        this.panStartY = event.clientY;
        this.panOriginX = this.panX;
        this.panOriginY = this.panY;
    },

    doPan(event) {
        if (!this.isPanning) return;
        event.preventDefault();
        const dx = event.clientX - this.panStartX;
        const dy = event.clientY - this.panStartY;
        this.panX = this.panOriginX + dx;
        this.panY = this.panOriginY + dy;
    },

    endPan() {
        this.isPanning = false;
    },

    startPanTouch(event) {
        if (!this.panMode || event.touches.length !== 1) return;
        this.isPanning = true;
        this.panStartX = event.touches[0].clientX;
        this.panStartY = event.touches[0].clientY;
        this.panOriginX = this.panX;
        this.panOriginY = this.panY;
    },

    doPanTouch(event) {
        if (!this.isPanning || event.touches.length !== 1) return;
        event.preventDefault();
        const dx = event.touches[0].clientX - this.panStartX;
        const dy = event.touches[0].clientY - this.panStartY;
        this.panX = this.panOriginX + dx;
        this.panY = this.panOriginY + dy;
    },
    currentView: 'front', // front, left, right
    imageLoading: false,
    viewImages: @js([
        'front' => asset(config('face_chart.face_2d_images.front')),
        'left' => asset(config('face_chart.face_2d_images.left')),
        'right' => asset(config('face_chart.face_2d_images.right')),
    ]),

    switchView(view) {
        if (this.currentView === view) return;

        this.imageLoading = true; // Hide image until sized correctly
        this.currentView = view;
        this.zoomLevel = 1;

        // Clear existing markers on canvas immediately
        if (this.fabricCanvas) {
            this.fabricCanvas.clear();
            this.fabricCanvas.renderAll();
        }

        // Call Livewire to update markers list server-side, then rebuild canvas
        const rebuildCanvas = () => {
            // Read updated markers from Livewire state
            if (this.$wire && this.$wire.$get) {
                this.config.markers = this.$wire.$get('markers') || [];
            }

            // Wait for new image to load (naturalWidth matches new image)
            const bgImage = document.getElementById('faceBackgroundImage');
            if (!bgImage) return;

            const doRebuild = () => {
                if (this.fabricCanvas) {
                    this.fabricCanvas.dispose();
                    this.fabricCanvas = null;
                    this.initialized = false;
                }
                this.setupFabricCanvas();
            };

            if (bgImage.complete && bgImage.naturalWidth > 0) {
                doRebuild();
            } else {
                bgImage.addEventListener('load', doRebuild, { once: true });
            }
        };

        if (this.$wire) {
            // Call Livewire and wait for response (returns filtered markers), then rebuild
            this.$wire.call('onSwitchView', view).then((markers) => {
                this.config.markers = markers || [];
                console.log('🔄 View switched to', view, ', markers:', this.config.markers.length);
                rebuildCanvas();
            });
        } else {
            rebuildCanvas();
        }
    },

    zoomIn() {
        this.zoomLevel = Math.min(this.maxZoom, this.zoomLevel + this.zoomStep);
    },

    zoomOut() {
        this.zoomLevel = Math.max(this.minZoom, this.zoomLevel - this.zoomStep);
    },

    resetZoom() {
        this.zoomLevel = 1;
        this.panX = 0;
        this.panY = 0;
    },

    handleWheelZoom(event) {
        const delta = event.deltaY > 0 ? -this.zoomStep : this.zoomStep;
        const newZoom = this.zoomLevel + delta;
        this.zoomLevel = Math.max(this.minZoom, Math.min(this.maxZoom, newZoom));
    },

    // Touch/Pinch zoom state
    initialPinchDistance: 0,
    initialPinchZoom: 1,

    getPinchDistance(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    },

    handleTouchStart(event) {
        if (event.touches.length === 2) {
            this.initialPinchDistance = this.getPinchDistance(event.touches);
            this.initialPinchZoom = this.zoomLevel;
        }
    },

    handleTouchMove(event) {
        if (event.touches.length === 2 && this.initialPinchDistance > 0) {
            const currentDistance = this.getPinchDistance(event.touches);
            const scale = currentDistance / this.initialPinchDistance;
            const newZoom = this.initialPinchZoom * scale;
            this.zoomLevel = Math.max(this.minZoom, Math.min(this.maxZoom, newZoom));
        }
    },

    handleTouchEnd(event) {
        if (event.touches.length < 2) {
            this.initialPinchDistance = 0;
        }
    },
    config: @js([
        'patientId' => $patientId,
        'appointmentId' => $appointmentId,
        'isEditing' => $isEditing,
        'imagePath' => asset(config('face_chart.face_2d_image_path')),
        'markers' => $markers,
    ]),

    startInitCheck() {
        console.log('🎨 Component mounted, starting initialization check...');

        // Preload all view images so switching is instant
        Object.values(this.viewImages).forEach(src => {
            const img = new Image();
            img.src = src;
        });

        const checkInterval = setInterval(() => {
            const el = document.getElementById('faceChart2DCanvas');
            if (el && el.offsetParent !== null && typeof fabric !== 'undefined') {
                console.log('✅ Canvas is visible and Fabric loaded, initializing...');
                clearInterval(checkInterval);
                this.initCanvas();
            }
        }, 100);

        // Listen for markers update from Livewire
        this.$wire.on('markersLoaded', (data) => {
            console.log('📥 Markers updated from Livewire:', data);
            this.config.markers = data.markers || data[0]?.markers || [];
            if (this.fabricCanvas) {
                // Remove all existing markers from canvas and reload
                this.clearCanvas();
                this.loadMarkers();
            }
        });
    },

    triggerInit() {
        console.log('🔔 Manual initialization triggered');
        setTimeout(() => {
            this.initCanvas();
        }, 200);
    },

    initCanvas() {
        if (this.initialized) {
            console.log('⚠️ Canvas already initialized, skipping');
            return;
        }

        console.log('🎨 Initializing 2D canvas...');
        console.log('📋 Config:', this.config);

        // Wait a bit to ensure element is fully rendered and visible
        this.$nextTick(() => {
            setTimeout(() => {
                this.setupFabricCanvas();
            }, 100);
        });
    },

    setupFabricCanvas() {
        const canvasEl = document.getElementById('faceChart2DCanvas');

        if (!canvasEl) {
            console.error('❌ Canvas element not found!');
            return;
        }

        console.log('✅ Canvas element found');

        // Check if element is actually visible
        if (canvasEl.offsetParent === null) {
            console.log('⏳ Canvas not visible yet (offsetParent is null), waiting...');
            setTimeout(() => this.setupFabricCanvas(), 100);
            return;
        }

        // Check if Fabric.js is loaded
        if (typeof fabric === 'undefined') {
            console.log('⏳ Waiting for Fabric.js...');
            setTimeout(() => this.setupFabricCanvas(), 100);
            return;
        }

        console.log('✅ Fabric.js loaded, version:', fabric.version);

        // Get parent canvas area container
        const canvasArea = canvasEl.closest('[class*="rounded-lg"]') || canvasEl.parentElement.parentElement;
        const container = document.getElementById('canvasContainer');

        const areaWidth = canvasArea ? canvasArea.offsetWidth : 0;
        const areaHeight = canvasArea ? canvasArea.offsetHeight : 0;
        const containerWidth = container ? container.offsetWidth : 0;
        const containerHeight = container ? container.offsetHeight : 0;

        console.log('📐 Canvas area (flex-1) size:', areaWidth, 'x', areaHeight);
        console.log('📐 Container size:', containerWidth, 'x', containerHeight);

        // Wait for proper dimensions (now that layout is always flex-row)
        if (areaWidth < 100 || areaHeight < 100) {
            console.log('⏳ Canvas area not properly sized yet, waiting...');
            setTimeout(() => this.setupFabricCanvas(), 100);
            return;
        }

        // Match canvas to image's natural aspect ratio
        const bgImage = document.getElementById('faceBackgroundImage');
        const maxSize = {{ config('face_chart.face_2d_canvas_max_width', 1200) }};

        // Wait for image to load to get natural dimensions
        if (bgImage && !bgImage.naturalWidth) {
            console.log('⏳ Waiting for background image to load...');
            bgImage.onload = () => this.setupFabricCanvas();
            return;
        }

        const imgAspect = bgImage && bgImage.naturalHeight ? (bgImage.naturalWidth / bgImage.naturalHeight) : 1;
        let canvasWidth, canvasHeight;

        if (imgAspect >= 1) {
            // Landscape image: constrain by width
            canvasWidth = Math.min(areaWidth, maxSize);
            canvasHeight = canvasWidth / imgAspect;
            if (canvasHeight > areaHeight) {
                canvasHeight = areaHeight;
                canvasWidth = canvasHeight * imgAspect;
            }
        } else {
            // Portrait image: constrain by height
            canvasHeight = Math.min(areaHeight, maxSize);
            canvasWidth = canvasHeight * imgAspect;
            if (canvasWidth > areaWidth) {
                canvasWidth = areaWidth;
                canvasHeight = canvasWidth / imgAspect;
            }
        }

        console.log('📐 Canvas size will be:', canvasWidth, 'x', canvasHeight, '(aspect:', imgAspect, ')');

        // Update canvas element size
        canvasEl.width = canvasWidth;
        canvasEl.height = canvasHeight;

        // Initialize canvas with transparent background
        this.fabricCanvas = new fabric.Canvas('faceChart2DCanvas', {
            width: canvasWidth,
            height: canvasHeight,
            selection: this.config.isEditing,
            backgroundColor: null, // Transparent - HTML img shows through
        });

        console.log('✅ Fabric canvas created:', this.fabricCanvas.width, 'x', this.fabricCanvas.height);

        // Ensure canvas wrapper has proper z-index and positioning
        const canvasWrapper = this.fabricCanvas.wrapperEl;
        if (canvasWrapper) {
            canvasWrapper.style.position = 'relative';
            canvasWrapper.style.margin = '0 auto';
            canvasWrapper.style.display = 'block';
            canvasWrapper.style.zIndex = '2'; // Must be above background image
            console.log('🔧 Fixed canvas wrapper positioning and z-index');
        }

        // Set background image to exact same size as canvas (fills since aspect matches)
        if (bgImage) {
            bgImage.style.width = canvasWidth + 'px';
            bgImage.style.height = canvasHeight + 'px';
            bgImage.style.objectFit = 'fill';
            console.log('✅ Background image set to', canvasWidth, 'x', canvasHeight);
        }

        // Image is sized correctly, show it now
        this.imageLoading = false;

        // Container will auto-size to fit the canvas (inline-block)
        console.log('📦 Container will auto-size to canvas dimensions');

        // Log canvas wrapper z-index after setup
        setTimeout(() => {
            const wrapper = this.fabricCanvas.wrapperEl;
            if (wrapper) {
                console.log('🎨 Canvas wrapper z-index:', window.getComputedStyle(wrapper).zIndex);
                console.log('🎨 Canvas wrapper position:', window.getComputedStyle(wrapper).position);
            }
        }, 100);

        this.initialized = true;

        // Setup canvas events
        this.setupCanvasEvents();

        // Load existing markers
        this.loadMarkers();

        // Set default tool now that canvas is ready
        this.selectTool('marker');

        // Watch for color/size changes to update pen brush in real-time
        this.$watch('drawingColor', (color) => {
            if (this.fabricCanvas && this.fabricCanvas.freeDrawingBrush) {
                this.fabricCanvas.freeDrawingBrush.color = color;
                console.log('🎨 Pen color updated:', color);
            }
        });

        this.$watch('strokeWidth', (width) => {
            if (this.fabricCanvas && this.fabricCanvas.freeDrawingBrush) {
                this.fabricCanvas.freeDrawingBrush.width = parseInt(width);
                console.log('📏 Pen width updated:', width);
            }
        });

        console.log('🎉 2D Face Chart ready!');
    },

    selectTool(tool) {
        console.log('🔧 Tool selected:', tool);
        this.selectedTool = tool;
        this.panMode = false; // Disable pan when any drawing tool is selected

        // Only configure canvas if it exists
        if (!this.fabricCanvas) {
            console.log('⚠️ Canvas not ready yet, tool will be applied when canvas initializes');
            return;
        }

        // Configure canvas based on tool
        if (tool === 'pen') {
            this.fabricCanvas.isDrawingMode = true;
            this.fabricCanvas.freeDrawingBrush.width = parseInt(this.strokeWidth);
            this.fabricCanvas.freeDrawingBrush.color = this.drawingColor;
        } else {
            this.fabricCanvas.isDrawingMode = false;
        }

        // Enable/disable selection - only allow selection with select tool
        const isSelectMode = (tool === 'select');
        const isEraserMode = (tool === 'eraser');

        this.fabricCanvas.selection = isSelectMode;
        this.fabricCanvas.forEachObject((obj) => {
            obj.selectable = isSelectMode;
            // Enable evented for both select and eraser modes (to detect clicks for deletion)
            obj.evented = isSelectMode || isEraserMode;
            // Change cursor for eraser mode
            if (isEraserMode) {
                obj.hoverCursor = 'pointer';
            }
        });

        // Deselect any active object when switching away from select tool
        if (!isSelectMode) {
            this.fabricCanvas.discardActiveObject();
        }

        // Set canvas cursor for eraser mode
        if (isEraserMode) {
            this.fabricCanvas.defaultCursor = 'crosshair';
            this.fabricCanvas.hoverCursor = 'pointer';
        } else {
            this.fabricCanvas.defaultCursor = 'default';
            this.fabricCanvas.hoverCursor = 'move';
        }

        this.fabricCanvas.renderAll();
    },

    onCanvasClick(e) {
        if (!this.config.isEditing) return;

        const pointer = this.fabricCanvas.getPointer(e.e);
        const x = pointer.x / this.fabricCanvas.width;
        const y = pointer.y / this.fabricCanvas.height;

        console.log('🖱️ Canvas clicked at:', x, y, 'Tool:', this.selectedTool);

        switch (this.selectedTool) {
            case 'marker':
                this.addMarker(pointer.x, pointer.y, x, y);
                break;
            case 'text':
                this.addTextAnnotation(pointer.x, pointer.y, x, y);
                break;
            // Arrow is handled by drag events (mouse:down, mouse:move, mouse:up)
        }
    },

    addMarker(canvasX, canvasY, normalizedX, normalizedY) {
        console.log('📍 Opening marker wizard at', canvasX, canvasY);

        // Send to Livewire - it opens the wizard instead of saving directly
        if (this.$wire) {
            this.$wire.canvasClick({
                x: normalizedX,
                y: normalizedY,
                type: 'marker',
                color: this.drawingColor
            });
        }
    },

    addTextAnnotation(canvasX, canvasY, normalizedX, normalizedY) {
        console.log('📝 Adding text at', canvasX, canvasY);

        const fontSize = Math.max(14, parseInt(this.strokeWidth) * 4);
        const text = new fabric.IText('Click to edit', {
            left: canvasX,
            top: canvasY,
            fontSize: fontSize,
            fill: this.drawingColor,
            fontFamily: 'Arial',
            editable: true,
            selectable: true, // Temporarily selectable for editing
            evented: true,
        });

        text.normalizedX = normalizedX;
        text.normalizedY = normalizedY;
        text.annotationType = 'text';

        this.fabricCanvas.add(text);
        this.fabricCanvas.setActiveObject(text);
        text.enterEditing();
        this.fabricCanvas.renderAll();

        // Save after editing and make non-selectable
        text.on('editing:exited', () => {
            // Make non-selectable after editing is done
            text.selectable = false;
            text.evented = false;
            this.fabricCanvas.renderAll();

            this.$wire.canvasClick({
                x: normalizedX,
                y: normalizedY,
                type: 'text',
                annotationText: text.text,
                annotationStyle: {
                    fontSize: text.fontSize,
                    fontFamily: text.fontFamily,
                    textColor: text.fill,
                    bold: text.fontWeight === 'bold',
                    italic: text.fontStyle === 'italic'
                }
            });
        });
    },

    startArrowDrag(canvasX, canvasY, normalizedX, normalizedY) {
        console.log('🏹 Arrow drag started at', canvasX, canvasY);
        this.isDrawingArrow = true;
        this.arrowStartPoint = { canvasX, canvasY, normalizedX, normalizedY };

        // Create preview arrow
        this.previewArrow = this.createArrowGroup(canvasX, canvasY, canvasX, canvasY, true);
        this.fabricCanvas.add(this.previewArrow);
        this.fabricCanvas.renderAll();
    },

    updateArrowPreview(canvasX, canvasY) {
        if (!this.isDrawingArrow || !this.previewArrow) return;

        // Remove old preview
        this.fabricCanvas.remove(this.previewArrow);

        // Create new preview at current position
        this.previewArrow = this.createArrowGroup(
            this.arrowStartPoint.canvasX,
            this.arrowStartPoint.canvasY,
            canvasX,
            canvasY,
            true
        );
        this.fabricCanvas.add(this.previewArrow);
        this.fabricCanvas.renderAll();
    },

    finishArrowDrag(canvasX, canvasY, normalizedX, normalizedY) {
        if (!this.isDrawingArrow) return;

        console.log('🏹 Arrow drag finished at', canvasX, canvasY);

        // Remove preview arrow
        if (this.previewArrow) {
            this.fabricCanvas.remove(this.previewArrow);
            this.previewArrow = null;
        }

        // Only create arrow if there's some distance
        const dx = canvasX - this.arrowStartPoint.canvasX;
        const dy = canvasY - this.arrowStartPoint.canvasY;
        const distance = Math.sqrt(dx * dx + dy * dy);

        if (distance > 10) {
            // Create final arrow as grouped object
            const arrow = this.createArrowGroup(
                this.arrowStartPoint.canvasX,
                this.arrowStartPoint.canvasY,
                canvasX,
                canvasY,
                false
            );
            this.fabricCanvas.add(arrow);
            this.fabricCanvas.renderAll();

            // Save to database
            console.log('💾 Saving arrow to Livewire...', {
                x: this.arrowStartPoint.normalizedX,
                y: this.arrowStartPoint.normalizedY,
                type: 'arrow',
                isEditing: this.config.isEditing,
                appointmentId: this.config.appointmentId
            });

            this.$wire.canvasClick({
                x: this.arrowStartPoint.normalizedX,
                y: this.arrowStartPoint.normalizedY,
                type: 'arrow',
                direction_x: normalizedX - this.arrowStartPoint.normalizedX,
                direction_y: normalizedY - this.arrowStartPoint.normalizedY,
            }).then(() => {
                console.log('✅ Arrow saved to database');
            }).catch((err) => {
                console.error('❌ Error saving arrow:', err);
            });

            console.log('✅ Arrow created as single group');
        }

        this.isDrawingArrow = false;
        this.arrowStartPoint = null;
    },

    createArrowGroup(x1, y1, x2, y2, isPreview = false) {
        // Calculate arrow properties
        const angle = Math.atan2(y2 - y1, x2 - x1);
        const strokeSize = parseInt(this.strokeWidth);
        const headLength = Math.max(15, strokeSize * 5);
        const headAngle = Math.PI / 6; // 30 degrees

        // Calculate arrowhead points
        const x3 = x2 - headLength * Math.cos(angle - headAngle);
        const y3 = y2 - headLength * Math.sin(angle - headAngle);
        const x4 = x2 - headLength * Math.cos(angle + headAngle);
        const y4 = y2 - headLength * Math.sin(angle + headAngle);

        // Create arrow as a single path (line + arrowhead)
        const pathData = `M ${x1} ${y1} L ${x2} ${y2} M ${x3} ${y3} L ${x2} ${y2} L ${x4} ${y4}`;

        const color = isPreview ? this.drawingColor + '99' : this.drawingColor;

        const arrow = new fabric.Path(pathData, {
            stroke: color,
            strokeWidth: strokeSize,
            fill: null,
            strokeLineCap: 'round',
            strokeLineJoin: 'round',
            selectable: false,
            evented: false,
        });

        arrow.annotationType = 'arrow';
        arrow.isPreview = isPreview;

        return arrow;
    },

    setupCanvasEvents() {
        // Mouse down event
        this.fabricCanvas.on('mouse:down', (e) => {
            if (!this.config.isEditing) return;

            // Handle eraser tool - delete clicked object
            if (this.selectedTool === 'eraser' && e.target) {
                const obj = e.target;
                console.log('🗑️ Eraser: deleting object', obj.annotationType, obj.markerId);

                // Remove from canvas
                this.fabricCanvas.remove(obj);
                this.fabricCanvas.renderAll();

                // If it's a saved marker, delete from database too
                if (obj.markerId && obj.isEditable) {
                    console.log('💾 Deleting from database, markerId:', obj.markerId);
                    this.$wire.onMarkerDelete(obj.markerId).then(() => {
                        console.log('✅ Deleted from database');
                    }).catch((err) => {
                        console.error('❌ Error deleting from database:', err);
                    });
                }
                return;
            }

            // Handle arrow tool - start drag
            if (this.selectedTool === 'arrow' && !e.target) {
                const pointer = this.fabricCanvas.getPointer(e.e);
                const normalizedX = pointer.x / this.fabricCanvas.width;
                const normalizedY = pointer.y / this.fabricCanvas.height;
                this.startArrowDrag(pointer.x, pointer.y, normalizedX, normalizedY);
                return;
            }

            // Handle other tools (only on empty canvas area)
            if (!e.target) {
                this.onCanvasClick(e);
            }
        });

        // Mouse move event - for arrow preview
        this.fabricCanvas.on('mouse:move', (e) => {
            if (this.selectedTool === 'arrow' && this.isDrawingArrow) {
                const pointer = this.fabricCanvas.getPointer(e.e);
                this.updateArrowPreview(pointer.x, pointer.y);
            }
        });

        // Mouse up event - finish arrow
        this.fabricCanvas.on('mouse:up', (e) => {
            if (this.selectedTool === 'arrow' && this.isDrawingArrow) {
                const pointer = this.fabricCanvas.getPointer(e.e);
                const normalizedX = pointer.x / this.fabricCanvas.width;
                const normalizedY = pointer.y / this.fabricCanvas.height;
                this.finishArrowDrag(pointer.x, pointer.y, normalizedX, normalizedY);
            }
        });

        // Save free-hand drawings after creation
        this.fabricCanvas.on('path:created', (e) => {
            const path = e.path;
            console.log('✏️ Free-hand drawing created');

            // Make non-selectable (only selectable with select tool)
            path.selectable = false;
            path.evented = false;

            // Store normalized path data
            const pathData = path.path.map(segment => {
                if (segment[0] === 'M' || segment[0] === 'L') {
                    return [
                        segment[0],
                        segment[1] / this.fabricCanvas.width,
                        segment[2] / this.fabricCanvas.height
                    ];
                }
                return segment;
            });

            path.normalizedPath = pathData;
            path.annotationType = 'drawing';

            // TODO: Save to database
            // this.$wire.saveDrawing({ path: pathData, ... });
        });

        // Handle object modifications
        this.fabricCanvas.on('object:modified', (e) => {
            console.log('📝 Object modified:', e.target);
            // TODO: Update in database
        });

        // Keyboard events for delete
        document.addEventListener('keydown', (e) => {
            if ((e.key === 'Delete' || e.key === 'Backspace') && this.config.isEditing) {
                // Don't delete if user is typing in a text field
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

                const activeObject = this.fabricCanvas.getActiveObject();
                if (activeObject && !activeObject.isEditing) {
                    e.preventDefault();
                    this.deleteSelected();
                }
            }
        });
    },

    deleteSelected() {
        const activeObject = this.fabricCanvas.getActiveObject();
        if (!activeObject) {
            console.log('⚠️ No object selected to delete');
            return;
        }

        // Helper to delete from database
        const deleteFromDb = (obj) => {
            if (obj.markerId && obj.isEditable) {
                console.log('💾 Deleting from database, markerId:', obj.markerId);
                this.$wire.onMarkerDelete(obj.markerId);
            }
        };

        // Handle multiple selection
        if (activeObject.type === 'activeSelection') {
            activeObject.forEachObject((obj) => {
                deleteFromDb(obj);
                this.fabricCanvas.remove(obj);
            });
            this.fabricCanvas.discardActiveObject();
        } else {
            deleteFromDb(activeObject);
            this.fabricCanvas.remove(activeObject);
        }

        this.fabricCanvas.renderAll();
        console.log('🗑️ Selected object(s) deleted');
    },

    loadMarkers() {
        console.log('📥 Loading', this.config.markers.length, 'markers');

        if (!this.fabricCanvas || !this.config.markers) return;

        // Render each marker based on its type
        this.config.markers.forEach(marker => {
            const canvasX = marker.x * this.fabricCanvas.width;
            const canvasY = marker.y * this.fabricCanvas.height;
            const markerType = marker.type || 'injection'; // 'type' field from toMarkerData()

            console.log('🎨 Rendering marker:', marker.id, markerType, canvasX, canvasY);

            switch (markerType) {
                case 'arrow':
                    this.renderArrow(marker, canvasX, canvasY);
                    break;
                case 'injection':
                case 'filler_point':
                case 'laser_spot':
                case 'thread_anchor':
                    this.renderMarker(marker, canvasX, canvasY);
                    break;
                case 'marking':
                    if (marker.annotationText) {
                        this.renderText(marker, canvasX, canvasY);
                    } else {
                        this.renderMarker(marker, canvasX, canvasY);
                    }
                    break;
                default:
                    this.renderMarker(marker, canvasX, canvasY);
            }
        });

        this.fabricCanvas.renderAll();
        console.log('✅ Loaded', this.config.markers.length, 'markers onto canvas');
    },

    renderMarker(marker, canvasX, canvasY) {
        const color = marker.color || '#FF6B6B';
        const size = marker.size || 8;
        const isSelectMode = this.selectedTool === 'select';
        const isEraserMode = this.selectedTool === 'eraser';

        const circle = new fabric.Circle({
            left: canvasX,
            top: canvasY,
            radius: size,
            fill: color,
            stroke: '#FFFFFF',
            strokeWidth: 2,
            originX: 'center',
            originY: 'center',
            selectable: isSelectMode,
            evented: isSelectMode || isEraserMode,
            hasControls: false,
        });

        circle.markerId = marker.id;
        circle.markerType = marker.type;
        circle.annotationType = 'marker';
        circle.isEditable = marker.isEditable;

        this.fabricCanvas.add(circle);
    },

    renderArrow(marker, canvasX, canvasY) {
        const color = marker.color || '#FF0000';
        const isSelectMode = this.selectedTool === 'select';
        const isEraserMode = this.selectedTool === 'eraser';

        // Calculate end point from direction
        const endX = canvasX + (marker.directionX || 0) * this.fabricCanvas.width;
        const endY = canvasY + (marker.directionY || 0) * this.fabricCanvas.height;

        // Create arrow using the same method as drawing
        const arrow = this.createArrowGroup(canvasX, canvasY, endX, endY, false);

        // Set selectability based on current tool
        arrow.selectable = isSelectMode;
        arrow.evented = isSelectMode || isEraserMode;

        arrow.markerId = marker.id;
        arrow.annotationType = 'arrow';
        arrow.isEditable = marker.isEditable;

        this.fabricCanvas.add(arrow);
    },

    renderText(marker, canvasX, canvasY) {
        const style = marker.annotationStyle || {};
        const isSelectMode = this.selectedTool === 'select';
        const isEraserMode = this.selectedTool === 'eraser';

        const text = new fabric.IText(marker.annotationText || '', {
            left: canvasX,
            top: canvasY,
            fontSize: style.fontSize || 16,
            fill: style.textColor || marker.color || '#000000',
            fontFamily: style.fontFamily || 'Arial',
            fontWeight: style.bold ? 'bold' : 'normal',
            fontStyle: style.italic ? 'italic' : 'normal',
            selectable: isSelectMode,
            evented: isSelectMode || isEraserMode,
            editable: false,
        });

        text.markerId = marker.id;
        text.annotationType = 'text';
        text.isEditable = marker.isEditable;

        this.fabricCanvas.add(text);
    },

    clearCanvas() {
        const objects = this.fabricCanvas.getObjects().slice(); // Clone array to avoid modification during iteration
        objects.forEach(obj => {
            this.fabricCanvas.remove(obj);
        });
        this.fabricCanvas.renderAll();
        console.log('🗑️ Canvas cleared');
    }
}));
</script>
@endscript
