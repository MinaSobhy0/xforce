<div class="flex flex-row gap-4" wire:ignore.self
     x-data="faceChart2DComponent()"
     x-init="startInitCheck()"
     @tab-switched-to-2d.window="console.log('🔔 Tab switched to 2D event received'); triggerInit()"
     @resize.window="if (fabricCanvas) fabricCanvas.renderAll()">
    {{-- Canvas Area (70%) --}}
    <div class="flex-1 relative bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden" style="min-height: 600px; min-width: 300px;">
        <div class="w-full h-full flex items-center justify-center p-4">
            <div id="canvasContainer" wire:ignore style="position: relative; display: inline-block;">
                <!-- Background Image (HTML layer behind canvas) - will be sized by JS -->
                <img
                    id="faceBackgroundImage"
                    src="{{ asset(config('face_chart.face_2d_image_path')) }}"
                    style="position: absolute; top: 0; left: 0; object-fit: contain; pointer-events: none; z-index: 1; display: block;"
                    alt="Face diagram"
                />

                <!-- Transparent Canvas layer on top -->
                <canvas
                    id="faceChart2DCanvas"
                    class="shadow-lg rounded border border-gray-300 dark:border-gray-600"
                    style="position: relative; display: block; z-index: 2; background: transparent;">
                </canvas>
            </div>
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

    {{-- Sidebar (30%) --}}
    <div class="w-80 flex-shrink-0 space-y-4 overflow-y-auto">
        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">
                {{ __('face_chart::face_chart.filters.title') }}
            </h3>

            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('face_chart::face_chart.filters.date_from') }}
                    </label>
                    <input
                        type="date"
                        wire:model.defer="filterDateFrom"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('face_chart::face_chart.filters.date_to') }}
                    </label>
                    <input
                        type="date"
                        wire:model.defer="filterDateTo"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('face_chart::face_chart.filters.region') }}
                    </label>
                    <select
                        wire:model.defer="filterRegion"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    >
                        <option value="">{{ __('face_chart::face_chart.filters.all_regions') }}</option>
                        @foreach($regions as $region)
                            <option value="{{ $region }}">
                                {{ __("face_chart::face_chart.regions.{$region}") }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('face_chart::face_chart.filters.type') }}
                    </label>
                    <select
                        wire:model.defer="filterType"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
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
                        class="flex-1 bg-primary-500 text-white rounded-md px-4 py-2 hover:bg-primary-600 transition"
                    >
                        {{ __('face_chart::face_chart.filters.apply') }}
                    </button>
                    <button
                        wire:click="clearFilters"
                        class="flex-1 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md px-4 py-2 hover:bg-gray-400 dark:hover:bg-gray-500 transition"
                    >
                        {{ __('face_chart::face_chart.filters.clear') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Statistics --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">
                {{ __('face_chart::face_chart.statistics.title') }}
            </h3>

            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.statistics.total_markers') }}</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $stats['total_markers'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.statistics.total_units') }}</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_units'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('face_chart::face_chart.statistics.appointments') }}</span>
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $stats['appointments_count'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        {{-- Marker List --}}
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
    selectedTool: 'marker', // marker, text, arrow, pen, select
    isDrawingArrow: false,
    arrowStartPoint: null,
    drawingColor: '#FF0000', // Default red
    strokeWidth: 3, // Default stroke width
    config: @js([
        'patientId' => $patientId,
        'appointmentId' => $appointmentId,
        'isEditing' => $isEditing,
        'imagePath' => asset(config('face_chart.face_2d_image_path')),
        'markers' => $markers,
    ]),

    startInitCheck() {
        console.log('🎨 Component mounted, starting initialization check...');
        const checkInterval = setInterval(() => {
            const el = document.getElementById('faceChart2DCanvas');
            if (el && el.offsetParent !== null && typeof fabric !== 'undefined') {
                console.log('✅ Canvas is visible and Fabric loaded, initializing...');
                clearInterval(checkInterval);
                this.initCanvas();
            }
        }, 100);
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

        // Get parent container that should have the flex-1 class (the main canvas area)
        const canvasArea = canvasEl.closest('.flex-1');
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

        // Use the smaller of area dimensions, capped at 800
        const canvasSize = Math.min(areaWidth - 40, areaHeight - 40, 800); // -40 for padding
        console.log('📐 Canvas size will be:', canvasSize, 'x', canvasSize);

        // Update canvas element size
        canvasEl.width = canvasSize;
        canvasEl.height = canvasSize;

        // Initialize canvas with transparent background
        this.fabricCanvas = new fabric.Canvas('faceChart2DCanvas', {
            width: canvasSize,
            height: canvasSize,
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

        // Set background image to exact same size as canvas
        const bgImage = document.getElementById('faceBackgroundImage');
        if (bgImage) {
            bgImage.style.width = canvasSize + 'px';
            bgImage.style.height = canvasSize + 'px';
            console.log('✅ Background image set to', canvasSize, 'x', canvasSize);
        }

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
            case 'arrow':
                this.handleArrowClick(pointer.x, pointer.y, x, y);
                break;
        }
    },

    addMarker(canvasX, canvasY, normalizedX, normalizedY) {
        console.log('📍 Adding marker at', canvasX, canvasY);

        const markerSize = Math.max(6, parseInt(this.strokeWidth) + 4);
        const marker = new fabric.Circle({
            left: canvasX,
            top: canvasY,
            radius: markerSize,
            fill: this.drawingColor,
            stroke: '#FFFFFF',
            strokeWidth: 2,
            originX: 'center',
            originY: 'center',
            selectable: false, // Only selectable with select tool
            evented: false,
            hasControls: false,
            hasBorders: true,
        });

        marker.normalizedX = normalizedX;
        marker.normalizedY = normalizedY;
        marker.markerType = 'injection_point';

        this.fabricCanvas.add(marker);
        this.fabricCanvas.renderAll();

        // Notify Livewire to save marker
        this.$wire.canvasClick({
            x: normalizedX,
            y: normalizedY,
            type: 'marker',
            color: this.drawingColor
        });
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

    handleArrowClick(canvasX, canvasY, normalizedX, normalizedY) {
        if (!this.isDrawingArrow) {
            // Start arrow
            console.log('🏹 Arrow start at', canvasX, canvasY);
            this.isDrawingArrow = true;
            this.arrowStartPoint = { canvasX, canvasY, normalizedX, normalizedY };

            // Show temporary marker
            const startMarker = new fabric.Circle({
                left: canvasX,
                top: canvasY,
                radius: 5,
                fill: '#00FF00',
                originX: 'center',
                originY: 'center',
                selectable: false,
                hasControls: false,
            });
            startMarker.isTemporary = true;
            this.fabricCanvas.add(startMarker);
            this.fabricCanvas.renderAll();
        } else {
            // End arrow
            console.log('🏹 Arrow end at', canvasX, canvasY);

            // Remove temporary markers first
            const objects = this.fabricCanvas.getObjects().slice();
            objects.forEach(obj => {
                if (obj.isTemporary) {
                    this.fabricCanvas.remove(obj);
                }
            });

            // Draw the arrow
            this.drawArrow(
                this.arrowStartPoint.canvasX,
                this.arrowStartPoint.canvasY,
                canvasX,
                canvasY
            );

            // Save to database
            this.$wire.canvasClick({
                x: this.arrowStartPoint.normalizedX,
                y: this.arrowStartPoint.normalizedY,
                type: 'arrow',
                direction_x: normalizedX - this.arrowStartPoint.normalizedX,
                direction_y: normalizedY - this.arrowStartPoint.normalizedY,
            });

            this.isDrawingArrow = false;
            this.arrowStartPoint = null;

            console.log('✅ Arrow completed, background should be visible');
        }
    },

    drawArrow(x1, y1, x2, y2) {
        console.log('🎨 Drawing arrow from', x1, y1, 'to', x2, y2);

        // Calculate arrow angle
        const angle = Math.atan2(y2 - y1, x2 - x1);
        const strokeSize = parseInt(this.strokeWidth);
        const headLength = Math.max(12, strokeSize * 4); // Scale arrowhead with stroke

        // Draw arrow line
        const line = new fabric.Line([x1, y1, x2, y2], {
            stroke: this.drawingColor,
            strokeWidth: strokeSize,
            selectable: false, // Only selectable with select tool
            evented: false,
        });
        line.annotationType = 'arrow';

        // Draw arrowhead
        const arrowHead = new fabric.Triangle({
            left: x2,
            top: y2,
            originX: 'center',
            originY: 'center',
            width: headLength,
            height: headLength,
            fill: this.drawingColor,
            angle: (angle * 180 / Math.PI) + 90,
            selectable: false, // Only selectable with select tool
            evented: false,
        });
        arrowHead.annotationType = 'arrow';

        // Add both parts separately
        this.fabricCanvas.add(line);
        this.fabricCanvas.add(arrowHead);
        this.fabricCanvas.renderAll();

        console.log('✅ Arrow added, total objects:', this.fabricCanvas.getObjects().length);
    },

    setupCanvasEvents() {
        // Click event for tools
        this.fabricCanvas.on('mouse:down', (e) => {
            if (!this.config.isEditing) return;

            // Handle eraser tool - delete clicked object
            if (this.selectedTool === 'eraser' && e.target) {
                console.log('🗑️ Eraser: deleting object');
                this.fabricCanvas.remove(e.target);
                this.fabricCanvas.renderAll();
                return;
            }

            // Handle other tools (only on empty canvas area)
            if (!e.target) {
                this.onCanvasClick(e);
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

        // Handle multiple selection
        if (activeObject.type === 'activeSelection') {
            activeObject.forEachObject((obj) => {
                this.fabricCanvas.remove(obj);
            });
            this.fabricCanvas.discardActiveObject();
        } else {
            this.fabricCanvas.remove(activeObject);
        }

        this.fabricCanvas.renderAll();
        console.log('🗑️ Selected object(s) deleted');
    },

    loadMarkers() {
        console.log('📥 Loading', this.config.markers.length, 'markers');
        // TODO: Render existing markers from database
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
