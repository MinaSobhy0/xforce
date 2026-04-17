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

    // Marker wizard state
    public bool $showMarkerWizard = false;
    public ?float $pendingMarkerX = null;
    public ?float $pendingMarkerY = null;
    public ?string $pendingMarkerColor = null;
    public ?int $wizardProductId = null;
    public float $wizardQty = 1;
    public ?string $wizardNotes = null;
    public array $availableConsumables = [];

    // Filter state
    public ?string $filterDateFrom = null;
    public ?string $filterDateTo = null;
    public ?string $filterRegion = null;
    public ?string $filterType = null;

    // Service injection
    protected FaceChartService $faceChartService;

    protected $listeners = [
        'refreshMarkers' => 'loadMarkers',
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

        // Delete all markers for this appointment + patient
        FaceChartMarker::where('patient_id', $this->patientId)
            ->where('appointment_id', $this->appointmentId)
            ->delete();

        $this->loadMarkers();
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
        $this->pendingMarkerX = $data['x'] ?? 0;
        $this->pendingMarkerY = $data['y'] ?? 0;
        $this->pendingMarkerColor = $data['color'] ?? '#FF0000';
        $this->wizardProductId = null;
        $this->wizardQty = 1;
        $this->wizardNotes = null;

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

        if (!$this->wizardProductId) {
            $this->addError('wizardProductId', 'Please select a consumable product.');
            return;
        }

        $product = \Modules\Inventory\Models\Product::find($this->wizardProductId);
        if (!$product) {
            $this->addError('wizardProductId', 'Product not found.');
            return;
        }

        // Find or create SessionConsumable for this appointment + product
        $sessionConsumable = \Modules\Booking\Models\SessionConsumable::firstOrNew([
            'appointment_id' => $this->appointmentId,
            'product_id' => $this->wizardProductId,
        ]);

        $unitCost = (int) ($product->cost_price_minor ?? 0);
        $newQty = ($sessionConsumable->quantity ?? 0) + $this->wizardQty;

        $sessionConsumable->fill([
            'quantity' => $newQty,
            'unit_cost_minor' => $unitCost,
            'total_cost_minor' => (int) round($newQty * $unitCost),
            'uom_id' => $product->sales_uom_id ?? null,
            'notes' => $this->wizardNotes ?: $sessionConsumable->notes,
            'is_deducted' => $sessionConsumable->is_deducted ?? false,
        ])->save();

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
            'color' => $this->pendingMarkerColor ?? '#FF0000',
            'product_name' => $product->name,
            'units' => $this->wizardQty,
            'notes' => $this->wizardNotes,
        ];

        $this->faceChartService->createMarker($markerData);
        $this->loadMarkers();

        // Notify parent page (treatment session) to refresh consumables list/pricing
        $this->dispatch('sessionConsumablesUpdated');

        $this->cancelMarkerWizard();
    }

    /**
     * Load consumable products available for the wizard dropdown.
     */
    protected function loadAvailableConsumables(): array
    {
        // All consumable products
        $products = \Modules\Inventory\Models\Product::where('is_consumable', true)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'cost_price_minor', 'sell_price_minor', 'sales_uom_id']);

        return $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => is_array($p->name) ? ($p->name['en'] ?? $p->name['ar'] ?? 'Unnamed') : $p->name,
                'price' => $p->sell_price_minor ? number_format($p->sell_price_minor / 100, 2) : '0.00',
            ];
        })->toArray();
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
        if (!$marker || ($this->appointmentId && $marker->appointment_id !== $this->appointmentId)) {
            return;
        }

        $this->faceChartService->deleteMarker($markerId);
        $this->loadMarkers();
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
