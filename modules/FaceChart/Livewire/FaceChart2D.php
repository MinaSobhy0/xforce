<?php

namespace Modules\FaceChart\Livewire;

use Livewire\Component;
use Modules\FaceChart\Models\FaceChartMarker;
use Modules\FaceChart\Services\FaceChartService;

class FaceChart2D extends Component
{
    public int $patientId;
    public ?int $appointmentId = null;
    public bool $isEditing = false;
    public array $markers = [];
    public string $currentView = 'front'; // front, left, right

    // Marker wizard state (handles both add and edit flows)
    public bool $showMarkerWizard = false;
    public string $wizardMode = 'add'; // 'add', 'edit', or 'view'
    public bool $wizardReadOnly = false; // true for markers from previous sessions
    public ?int $wizardMarkerId = null; // set when editing an existing marker
    public ?float $pendingMarkerX = null;
    public ?float $pendingMarkerY = null;
    public ?string $pendingMarkerColor = null;
    public ?int $wizardProductId = null;
    public float $wizardQty = 1;
    public ?string $wizardNotes = null;
    public string $wizardColor = '#FF0000';
    public int $wizardRotation = 0;
    public float $wizardSize = 1.0;
    public ?string $wizardRegion = null;
    public array $availableConsumables = [];

    // Marker detail/edit state
    public bool $showMarkerDetail = false;
    public ?int $detailMarkerId = null;
    public ?string $detailProductName = null;
    public ?string $detailProductUnit = null;
    public ?string $detailMarkerType = null;
    public ?string $detailCreatedAt = null;
    public float $detailEditQty = 0;
    public ?string $detailEditNotes = null;
    public string $detailEditColor = '#FF0000';
    public int $detailEditRotation = 0;
    public float $detailEditSize = 1.0;

    // Filter state
    public ?string $filterDateFrom = null;
    public ?string $filterDateTo = null;
    public ?string $filterRegion = null;
    public ?string $filterType = null;

    // Service injection
    protected FaceChartService $faceChartService;

    protected $listeners = [
        'refreshMarkers' => 'loadMarkers',
        'faceChartMarkersRefresh' => 'loadMarkers',
        'canvasClick' => 'onCanvasClick',
        'markerUpdate' => 'onMarkerUpdate',
        'markerDelete' => 'onMarkerDelete',
        'textAnnotationSave' => 'onTextAnnotationSave',
        'switchView' => 'onSwitchView',
    ];

    public function onSwitchView(string $view): array
    {
        if (!in_array($view, ['front', 'left', 'right'])) {
            return $this->markers;
        }
        $this->currentView = $view;
        $this->loadMarkers();
        return $this->markers;
    }

    public function clearAllMarkers(): void
    {
        if (!$this->isEditing || !$this->appointmentId) {
            return;
        }

        // Delete markers one-by-one so the deleting event fires
        // and linked SessionConsumables get their quantities adjusted.
        FaceChartMarker::where('patient_id', $this->patientId)
            ->where('appointment_id', $this->appointmentId)
            ->get()
            ->each(fn ($marker) => $marker->delete());

        $this->loadMarkers();
        $this->dispatch('sessionConsumablesRefresh');
    }

    public function boot(FaceChartService $faceChartService): void
    {
        $this->faceChartService = $faceChartService;
    }

    public function mount(int $patientId, ?int $appointmentId = null, bool $editMode = false): void
    {
        $this->patientId = $patientId;
        $this->appointmentId = $appointmentId;
        $this->isEditing = $editMode;
        $this->loadMarkers();
    }

    public function loadMarkers(): void
    {
        // Load only 2D markers
        $allMarkers = $this->faceChartService->getMarkersForVisualization(
            $this->patientId,
            $this->appointmentId,
            $this->filterDateFrom,
            $this->filterDateTo,
            $this->filterRegion,
            $this->filterType
        );

        // Filter to only 2D markers for current view (e.g., 2d_front, 2d_left, 2d_right)
        $expectedView = '2d_' . $this->currentView;
        $this->markers = array_values(array_filter($allMarkers, function ($marker) use ($expectedView) {
            $viewType = $marker['viewType'] ?? '3d';
            // Match exact view, or legacy '2d' markers shown on 'front' view
            return $viewType === $expectedView
                || ($viewType === '2d' && $this->currentView === 'front');
        }));

        $this->dispatch('markersLoaded', markers: $this->markers);
    }

    public function canvasClick(array $data): void
    {
        if (!$this->isEditing || !$this->appointmentId) {
            \Log::warning('FaceChart2D: canvasClick rejected', [
                'isEditing' => $this->isEditing,
                'appointmentId' => $this->appointmentId,
            ]);
            return;
        }

        $type = $data['type'] ?? 'marker';

        // For the marker tool, open the wizard instead of saving immediately
        if ($type === 'marker') {
            $this->openMarkerWizard($data);
            return;
        }

        // Map 'type' to 'marker_type' for database
        $markerType = $type;
        if ($markerType === 'marker') {
            $markerType = 'injection';
        }

        $markerData = [
            'patient_id' => $this->patientId,
            'appointment_id' => $this->appointmentId,
            'view_type' => '2d_' . $this->currentView,
            'x' => $data['x'] ?? 0,
            'y' => $data['y'] ?? 0,
            'z' => 0,
            'marker_type' => $markerType,
            'color' => $data['color'] ?? '#FF0000',
            'direction_x' => $data['direction_x'] ?? null,
            'direction_y' => $data['direction_y'] ?? null,
            'annotation_text' => $data['annotationText'] ?? null,
            'annotation_style' => $data['annotationStyle'] ?? null,
        ];

        $this->faceChartService->createMarker($markerData);
        $this->loadMarkers();
    }

    /**
     * Open the consumable-selection wizard after clicking with marker tool.
     */
    public function openMarkerWizard(array $data): void
    {
        $this->wizardMode = 'add';
        $this->wizardMarkerId = null;
        $this->pendingMarkerX = $data['x'] ?? 0;
        $this->pendingMarkerY = $data['y'] ?? 0;
        $this->pendingMarkerColor = $data['color'] ?? '#FF0000';
        $this->wizardProductId = null;
        $this->wizardQty = 1;
        $this->wizardNotes = null;
        $this->wizardColor = $data['color'] ?? '#FF0000';
        $this->wizardRotation = 0;
        $this->wizardSize = 1.0;
        // Suggest a region based on where the user clicked
        $this->wizardRegion = $this->guessRegion((float) $this->pendingMarkerX, (float) $this->pendingMarkerY);

        // Load available consumables (session's + all consumable products)
        $this->availableConsumables = $this->loadAvailableConsumables();

        $this->showMarkerWizard = true;
    }

    /**
     * Cancel the wizard without creating a marker.
     */
    public function cancelMarkerWizard(): void
    {
        $this->showMarkerWizard = false;
        $this->wizardMode = 'add';
        $this->wizardReadOnly = false;
        $this->wizardMarkerId = null;
        $this->pendingMarkerX = null;
        $this->pendingMarkerY = null;
    }

    /**
     * Confirm the wizard: create marker + link/create SessionConsumable.
     */
    public function confirmMarkerWizard(): void
    {
        if (!$this->isEditing || !$this->appointmentId || $this->pendingMarkerX === null) {
            return;
        }

        if ($this->wizardReadOnly) {
            $this->cancelMarkerWizard();
            return;
        }

        if (!$this->wizardProductId) {
            $this->addError('wizardProductId', 'Please select a consumable product.');
            return;
        }

        $product = \Modules\Inventory\Models\Product::find($this->wizardProductId);
        if (!$product) {
            $this->addError('wizardProductId', 'Product not found.');
            return;
        }

        // Edit mode: update the existing marker and refresh
        if ($this->wizardMode === 'edit' && $this->wizardMarkerId) {
            $this->updateWizardMarker();
            return;
        }

        $appointment = \Modules\Booking\Models\Appointment::find($this->appointmentId);

        // Merge with existing row for same (appointment_id, product_id) if present
        $sessionConsumable = \Modules\Booking\Models\SessionConsumable::firstOrNew([
            'appointment_id' => $this->appointmentId,
            'product_id' => $this->wizardProductId,
        ]);

        $wasMerged = $sessionConsumable->exists;
        $unitCost = (int) ($product->cost_price_minor ?? 0);

        // Current consumable quantity acts as a "budget"; only grow it when
        // the total of all markers (existing + new) exceeds that budget.
        $currentQty = (float) ($sessionConsumable->quantity ?? 0);
        $existingMarkersQty = (float) \Modules\FaceChart\Models\FaceChartMarker::query()
            ->where('appointment_id', $this->appointmentId)
            ->where('product_id', $this->wizardProductId)
            ->sum('units');
        $newTotalMarkersQty = $existingMarkersQty + (float) $this->wizardQty;

        $newQty = $newTotalMarkersQty > $currentQty ? $newTotalMarkersQty : $currentQty;

        $serviceQty = (float) ($appointment->quantity ?? 1);
        $newBaseQty = $serviceQty > 0 ? $newQty / $serviceQty : $newQty;

        if (!$wasMerged) {
            $sessionConsumable->tenant_id = $appointment?->tenant_id;
            $sessionConsumable->branch_id = $appointment?->branch_id;
            $sessionConsumable->uom_id = $product->sales_uom_id ?? null;
            $sessionConsumable->unit = $product->unit_abbreviation;
            $sessionConsumable->unit_cost_minor = $unitCost;
            $sessionConsumable->created_by = auth()->id();
            $sessionConsumable->is_deducted = false;
        }

        $sessionConsumable->quantity = $newQty;
        $sessionConsumable->base_quantity = $newBaseQty;
        if ($this->wizardNotes) {
            $sessionConsumable->notes = $this->wizardNotes;
        }
        $sessionConsumable->save();

        // Create the marker with product and consumable links
        $markerData = [
            'patient_id' => $this->patientId,
            'appointment_id' => $this->appointmentId,
            'product_id' => $this->wizardProductId,
            'session_consumable_id' => $sessionConsumable->id,
            'view_type' => '2d_' . $this->currentView,
            'x' => $this->pendingMarkerX,
            'y' => $this->pendingMarkerY,
            'z' => 0,
            'marker_type' => 'injection',
            'color' => $this->wizardColor ?: ($this->pendingMarkerColor ?? '#FF0000'),
            'size' => $this->wizardSize,
            'face_region' => $this->wizardRegion,
            'product_name' => is_array($product->name) ? ($product->name['en'] ?? '') : $product->name,
            'units' => $this->wizardQty,
            'notes' => $this->wizardNotes,
            'metadata' => [
                'rotation' => (int) $this->wizardRotation,
            ],
        ];

        $this->faceChartService->createMarker($markerData);
        $this->loadMarkers();

        // Notify parent page (treatment session) to refresh consumables list/pricing
        $this->dispatch('sessionConsumablesRefresh');

        $this->cancelMarkerWizard();
    }

    /**
     * Update an existing marker via the wizard (edit mode).
     */
    protected function updateWizardMarker(): void
    {
        $marker = FaceChartMarker::find($this->wizardMarkerId);
        if (!$marker || ($this->appointmentId && $marker->appointment_id !== $this->appointmentId)) {
            return;
        }

        $marker->product_id = $this->wizardProductId;
        $marker->units = $this->wizardQty;
        $marker->notes = $this->wizardNotes;
        $marker->color = $this->wizardColor ?: '#FF0000';
        $marker->size = $this->wizardSize > 0 ? $this->wizardSize : 1.0;
        $marker->face_region = $this->wizardRegion;
        $metadata = is_array($marker->metadata) ? $marker->metadata : [];
        $metadata['rotation'] = (int) $this->wizardRotation;
        $marker->metadata = $metadata;
        $marker->save();

        // Recalculate linked SessionConsumable (grow-only)
        if ($marker->session_consumable_id && $marker->product_id) {
            $sc = \Modules\Booking\Models\SessionConsumable::find($marker->session_consumable_id);
            if ($sc) {
                $totalMarkersQty = (float) FaceChartMarker::query()
                    ->where('appointment_id', $marker->appointment_id)
                    ->where('product_id', $marker->product_id)
                    ->sum('units');

                if ($totalMarkersQty > (float) $sc->quantity) {
                    $sc->quantity = $totalMarkersQty;
                    $serviceQty = (float) ($sc->appointment?->quantity ?? 1);
                    $sc->base_quantity = $serviceQty > 0 ? $totalMarkersQty / $serviceQty : $totalMarkersQty;
                    $sc->save();
                }
            }
        }

        $this->loadMarkers();
        $this->dispatch('sessionConsumablesRefresh');
        $this->cancelMarkerWizard();
    }

    /**
     * Delete the marker currently open in the wizard (edit mode).
     */
    public function deleteWizardMarker(): void
    {
        if (!$this->isEditing || $this->wizardMode !== 'edit' || $this->wizardReadOnly || !$this->wizardMarkerId) {
            return;
        }

        $marker = FaceChartMarker::find($this->wizardMarkerId);
        if (!$marker || ($this->appointmentId && $marker->appointment_id !== $this->appointmentId)) {
            return;
        }

        $this->faceChartService->deleteMarker($marker->id);
        $this->loadMarkers();
        $this->dispatch('sessionConsumablesRefresh');
        $this->cancelMarkerWizard();
    }

    /**
     * Open the wizard pre-filled with an existing marker's data (edit mode).
     */
    public function showMarkerDetails(int $markerId): void
    {
        $marker = FaceChartMarker::with('product', 'sessionConsumable')->find($markerId);

        if (!$marker || $marker->patient_id !== $this->patientId) {
            return;
        }

        // Markers from previous sessions are view-only
        $isFromCurrentSession = $this->appointmentId && $marker->appointment_id === $this->appointmentId;
        $this->wizardReadOnly = !$isFromCurrentSession;
        $this->wizardMode = $isFromCurrentSession ? 'edit' : 'view';
        $this->wizardMarkerId = $marker->id;
        $this->pendingMarkerX = (float) $marker->x;
        $this->pendingMarkerY = (float) $marker->y;
        $this->pendingMarkerColor = $marker->color ?: '#FF0000';
        $this->wizardProductId = $marker->product_id;
        $this->wizardQty = (float) ($marker->units ?? 1);
        $this->wizardNotes = $marker->notes;
        $this->wizardColor = $marker->color ?: '#FF0000';
        $this->wizardRotation = (int) (is_array($marker->metadata) ? ($marker->metadata['rotation'] ?? 0) : 0);
        $this->wizardSize = (float) ($marker->size ?: 1.0);
        $this->wizardRegion = $marker->face_region;

        $this->availableConsumables = $this->loadAvailableConsumables();

        $this->showMarkerWizard = true;
    }

    /**
     * Guess a face region from normalized (x, y) coordinates + current view.
     * Rough zones — the user can override via the dropdown.
     */
    protected function guessRegion(float $x, float $y): ?string
    {
        $view = $this->currentView;

        // Vertical bands (y): 0 = top of canvas. Typical face anatomy layout.
        // Front view has regions on both sides; left/right views expose more of one profile.
        if ($y < 0.18) return 'forehead';
        if ($y < 0.28) {
            // Could be forehead or temples depending on horizontal position
            if ($view === 'front' && ($x < 0.28 || $x > 0.72)) return 'temples';
            return 'forehead';
        }
        if ($y < 0.36) {
            if ($view === 'front' && ($x > 0.42 && $x < 0.58)) return 'glabella';
            return 'temples';
        }
        if ($y < 0.45) {
            if ($view === 'front') {
                if ($x > 0.42 && $x < 0.58) return 'nose';
                if ($x < 0.30 || $x > 0.70) return 'crow_feet';
                return 'upper_eyelid';
            }
            return 'crow_feet';
        }
        if ($y < 0.55) {
            if ($view === 'front' && $x > 0.42 && $x < 0.58) return 'nose';
            return 'cheeks';
        }
        if ($y < 0.62) {
            if ($view === 'front' && $x > 0.40 && $x < 0.60) return 'upper_lip';
            return 'nasolabial';
        }
        if ($y < 0.68) {
            if ($view === 'front' && $x > 0.40 && $x < 0.60) return 'lower_lip';
            return 'marionette';
        }
        if ($y < 0.78) return 'chin';
        if ($y < 0.85) return 'jawline';
        return 'neck';
    }

    public function closeMarkerDetail(): void
    {
        $this->showMarkerDetail = false;
        $this->detailMarkerId = null;
        $this->detailProductName = null;
        $this->detailEditQty = 0;
        $this->detailEditNotes = null;
    }

    /**
     * Save edits to a marker (qty + notes), and recalculate the linked
     * SessionConsumable quantity using the same budget rule as the wizard.
     */
    public function saveMarkerEdit(): void
    {
        if (!$this->isEditing || !$this->detailMarkerId) {
            return;
        }

        $marker = FaceChartMarker::find($this->detailMarkerId);
        if (!$marker || ($this->appointmentId && $marker->appointment_id !== $this->appointmentId)) {
            return;
        }

        $marker->units = $this->detailEditQty;
        $marker->notes = $this->detailEditNotes;
        $marker->color = $this->detailEditColor ?: '#FF0000';
        $marker->size = $this->detailEditSize > 0 ? $this->detailEditSize : 1.0;
        $metadata = is_array($marker->metadata) ? $marker->metadata : [];
        $metadata['rotation'] = (int) $this->detailEditRotation;
        $marker->metadata = $metadata;
        $marker->save();

        // Recalculate the linked session consumable's quantity (grow-only)
        if ($marker->session_consumable_id && $marker->product_id) {
            $sc = \Modules\Booking\Models\SessionConsumable::find($marker->session_consumable_id);
            if ($sc) {
                $totalMarkersQty = (float) FaceChartMarker::query()
                    ->where('appointment_id', $marker->appointment_id)
                    ->where('product_id', $marker->product_id)
                    ->sum('units');

                if ($totalMarkersQty > (float) $sc->quantity) {
                    $sc->quantity = $totalMarkersQty;
                    $serviceQty = (float) ($sc->appointment?->quantity ?? 1);
                    $sc->base_quantity = $serviceQty > 0 ? $totalMarkersQty / $serviceQty : $totalMarkersQty;
                    $sc->save();
                }
            }
        }

        $this->loadMarkers();
        $this->dispatch('sessionConsumablesRefresh');
        $this->closeMarkerDetail();
    }

    /**
     * Delete the currently viewed marker. The linked SessionConsumable's
     * quantity is left unchanged (see plan: auto-decrement is out of scope).
     */
    public function deleteMarkerFromDetail(): void
    {
        if (!$this->isEditing || !$this->detailMarkerId) {
            return;
        }

        $marker = FaceChartMarker::find($this->detailMarkerId);
        if (!$marker || ($this->appointmentId && $marker->appointment_id !== $this->appointmentId)) {
            return;
        }

        $this->faceChartService->deleteMarker($marker->id);
        $this->loadMarkers();
        $this->dispatch('sessionConsumablesRefresh');
        $this->closeMarkerDetail();
    }

    /**
     * Load consumable products available for the wizard dropdown.
     * Mirrors TreatmentSession::getAvailableConsumables: only show products in stock
     * unless their category allows negative stock.
     */
    protected function loadAvailableConsumables(): array
    {
        $branchId = null;
        if ($this->appointmentId) {
            $branchId = \Modules\Booking\Models\Appointment::where('id', $this->appointmentId)->value('branch_id');
        }

        $stockLocation = $branchId
            ? \Modules\Inventory\Models\StockLocation::getTreatmentDefaultLocation($branchId)
            : null;

        $products = \Modules\Inventory\Models\Product::query()
            ->where('is_active', true)
            ->where('is_consumable', true)
            ->with(['salesUom', 'category'])
            ->get();

        $stockLevels = [];
        if ($stockLocation) {
            $stockLevels = \Modules\Inventory\Models\StockLevel::where('location_id', $stockLocation->id)
                ->pluck('quantity_on_hand', 'product_id')
                ->toArray();
        }

        return $products
            ->filter(function ($product) use ($stockLevels) {
                $stockQty = $stockLevels[$product->id] ?? 0;
                if ($stockQty > 0) {
                    return true;
                }
                return $product->category?->allow_negative_stock ?? false;
            })
            ->map(function ($product) use ($stockLevels) {
                $stockQty = $stockLevels[$product->id] ?? 0;
                $stockUom = $product->salesUom?->abbreviation ?? 'pcs';

                return [
                    'id' => $product->id,
                    'name' => is_array($product->name)
                        ? ($product->name[app()->getLocale()] ?? $product->name['en'] ?? $product->name['ar'] ?? 'Unnamed')
                        : $product->name,
                    'price' => $product->sell_price_minor ? number_format($product->sell_price_minor / 100, 2) : '0.00',
                    'stock_qty' => $stockQty,
                    'stock_uom' => $stockUom,
                ];
            })
            ->values()
            ->toArray();
    }

    public function onCanvasClick(array $data): void
    {
        // Alias for event listener
        $this->canvasClick($data);
    }

    public function onMarkerUpdate(int $markerId, array $data): void
    {
        $marker = FaceChartMarker::find($markerId);
        if (!$marker || ($this->appointmentId && $marker->appointment_id !== $this->appointmentId)) {
            return;
        }

        $this->faceChartService->updateMarker($markerId, $data);
        $this->loadMarkers();
    }

    public function onMarkerDelete(int $markerId): void
    {
        $marker = FaceChartMarker::find($markerId);
        // Only allow deletion of markers from the current appointment
        if (!$marker || !$this->appointmentId || $marker->appointment_id !== $this->appointmentId) {
            return;
        }

        $this->faceChartService->deleteMarker($markerId);
        $this->loadMarkers();
        $this->dispatch('sessionConsumablesRefresh');
    }

    public function saveDrawing(array $data): void
    {
        if (!$this->isEditing || !$this->appointmentId) {
            return;
        }

        // Save free-hand drawing path
        $markerData = array_merge($data, [
            'patient_id' => $this->patientId,
            'appointment_id' => $this->appointmentId,
            'view_type' => '2d_' . $this->currentView,
            'z' => 0,
            'marker_type' => 'marking',
        ]);

        $this->faceChartService->createMarker($markerData);
        $this->loadMarkers();
    }

    public function onTextAnnotationSave(array $data): void
    {
        if (!$this->isEditing || !$this->appointmentId) {
            return;
        }

        $markerData = array_merge($data, [
            'patient_id' => $this->patientId,
            'appointment_id' => $this->appointmentId,
            'view_type' => '2d_' . $this->currentView,
            'z' => 0,
            'marker_type' => 'marking', // Text annotations are marked as 'marking'
        ]);

        if (isset($data['id']) && $data['id']) {
            // Update existing annotation
            $this->faceChartService->updateMarker($data['id'], $markerData);
        } else {
            // Create new annotation
            $this->faceChartService->createMarker($markerData);
        }

        $this->loadMarkers();
    }

    public function applyFilters(): void
    {
        $this->loadMarkers();
    }

    public function clearFilters(): void
    {
        $this->filterDateFrom = null;
        $this->filterDateTo = null;
        $this->filterRegion = null;
        $this->filterType = null;
        $this->loadMarkers();
    }

    public function render()
    {
        return view('face_chart::livewire.face-chart-2d', [
            'regions' => FaceChartMarker::REGIONS,
            'markerTypes' => FaceChartMarker::MARKER_TYPES,
            'unitTypes' => FaceChartMarker::UNIT_TYPES,
            'stats' => $this->faceChartService->getPatientStats($this->patientId),
        ]);
    }
}
