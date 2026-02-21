<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            canvas: null,
            ctx: null,
            isDrawing: false,
            lastX: 0,
            lastY: 0,
            isEmpty: true,
            canvasWidth: {{ $getCanvasWidth() }},
            canvasHeight: {{ $getCanvasHeight() }},
            strokeColor: '{{ $getStrokeColor() }}',
            strokeWidth: {{ $getStrokeWidth() }},
            backgroundColor: '{{ $getBackgroundColor() }}',

            init() {
                this.canvas = this.$refs.canvas;
                this.ctx = this.canvas.getContext('2d');
                this.setupCanvas();

                // Load existing signature if present
                if (this.state) {
                    this.loadSignature(this.state);
                }
            },

            setupCanvas() {
                // Set canvas size
                this.canvas.width = this.canvasWidth;
                this.canvas.height = this.canvasHeight;

                // Fill background
                this.ctx.fillStyle = this.backgroundColor;
                this.ctx.fillRect(0, 0, this.canvasWidth, this.canvasHeight);

                // Set stroke style
                this.ctx.strokeStyle = this.strokeColor;
                this.ctx.lineWidth = this.strokeWidth;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
            },

            getCoordinates(e) {
                const rect = this.canvas.getBoundingClientRect();
                const scaleX = this.canvas.width / rect.width;
                const scaleY = this.canvas.height / rect.height;

                if (e.touches && e.touches.length > 0) {
                    return {
                        x: (e.touches[0].clientX - rect.left) * scaleX,
                        y: (e.touches[0].clientY - rect.top) * scaleY
                    };
                }

                return {
                    x: (e.clientX - rect.left) * scaleX,
                    y: (e.clientY - rect.top) * scaleY
                };
            },

            startDrawing(e) {
                e.preventDefault();
                this.isDrawing = true;
                const coords = this.getCoordinates(e);
                this.lastX = coords.x;
                this.lastY = coords.y;
                this.isEmpty = false;
            },

            draw(e) {
                if (!this.isDrawing) return;
                e.preventDefault();

                const coords = this.getCoordinates(e);

                this.ctx.beginPath();
                this.ctx.moveTo(this.lastX, this.lastY);
                this.ctx.lineTo(coords.x, coords.y);
                this.ctx.stroke();

                this.lastX = coords.x;
                this.lastY = coords.y;
            },

            stopDrawing(e) {
                if (this.isDrawing) {
                    e.preventDefault();
                    this.isDrawing = false;
                    this.saveSignature();
                }
            },

            clearCanvas() {
                this.ctx.fillStyle = this.backgroundColor;
                this.ctx.fillRect(0, 0, this.canvasWidth, this.canvasHeight);
                this.isEmpty = true;
                this.state = null;
            },

            saveSignature() {
                if (!this.isEmpty) {
                    this.state = this.canvas.toDataURL('image/png');
                }
            },

            loadSignature(dataUrl) {
                if (!dataUrl) return;

                const img = new Image();
                img.onload = () => {
                    this.setupCanvas();
                    this.ctx.drawImage(img, 0, 0);
                    this.isEmpty = false;
                };
                img.src = dataUrl;
            }
        }"
        class="signature-pad-container"
    >
        {{-- Signature Canvas --}}
        <div class="relative">
            <canvas
                x-ref="canvas"
                @mousedown="startDrawing"
                @mousemove="draw"
                @mouseup="stopDrawing"
                @mouseleave="stopDrawing"
                @touchstart="startDrawing"
                @touchmove="draw"
                @touchend="stopDrawing"
                @touchcancel="stopDrawing"
                class="w-full border-2 border-gray-300 dark:border-gray-600 rounded-lg cursor-crosshair touch-none"
                style="max-width: {{ $getCanvasWidth() }}px; aspect-ratio: {{ $getCanvasWidth() }} / {{ $getCanvasHeight() }};"
            ></canvas>

            {{-- Signature Line Guide --}}
            <div
                class="absolute bottom-8 left-4 right-4 border-b border-dashed border-gray-400 dark:border-gray-500 pointer-events-none"
            ></div>

            {{-- Sign Here Label --}}
            <span
                x-show="isEmpty"
                class="absolute bottom-10 left-1/2 transform -translate-x-1/2 text-sm text-gray-400 dark:text-gray-500 pointer-events-none"
            >
                {{ __('patients::patients.consent.sign_here') }}
            </span>
        </div>

        {{-- Controls --}}
        <div class="flex items-center justify-between mt-3">
            <div class="flex items-center gap-2">
                @if ($getShowClearButton())
                    <button
                        type="button"
                        @click="clearCanvas()"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        {{ __('patients::patients.consent.clear_signature') }}
                    </button>
                @endif
            </div>

            {{-- Status Indicator --}}
            <div class="flex items-center gap-2 text-sm">
                <template x-if="!isEmpty">
                    <span class="flex items-center gap-1 text-green-600 dark:text-green-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        {{ __('patients::patients.consent.signature_captured') }}
                    </span>
                </template>
                <template x-if="isEmpty">
                    <span class="flex items-center gap-1 text-gray-500 dark:text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                        {{ __('patients::patients.consent.awaiting_signature') }}
                    </span>
                </template>
            </div>
        </div>

        {{-- Helper Text --}}
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {{ __('patients::patients.consent.signature_instruction') }}
        </p>
    </div>
</x-dynamic-component>
