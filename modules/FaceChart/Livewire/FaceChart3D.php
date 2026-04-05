<?php

namespace Modules\FaceChart\Livewire;

use Livewire\Component;
use Modules\FaceChart\Models\FaceChartMarker;
use Modules\FaceChart\Services\FaceChartService;
use Modules\Patients\Models\Patient;

class FaceChart3D extends Component
{
    /**
     * The patient whose face chart we're viewing.
     */
    public int $patientId;
    public ?string $patientName = null;

    /**
     * Current appointment context (for editing).
     */
    public ?int $appointmentId = null;

    /**
     * Current service context (for markers).
     */
    public ?int $serviceId = null;
    public ?int $serviceCategoryId = null;
    public ?string $serviceName = null;

    /**
     * All markers for visualization.
     */
    public array $markers = [];

    /**
     * Editing state.
     */
    public bool $isEditing = false;
    public ?int $selectedMarkerId = null;

    /**
     * Filter state.
     */
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public ?string $regionFilter = null;
    public ?string $typeFilter = null;

    /**
     * New marker form data.
     */
    public array $newMarker = [
        'marker_type' => 'injection',
        'product_name' => '',
        'units' => null,
        'unit_type' => 'units',
        'face_region' => null,
        'notes' => '',
        'color' => '#3B82F6',
        'depth_mm' => null,
        'direction_x' => null,
        'direction_y' => null,
        'direction_z' => null,
    ];

    /**
     * Selected marker data for viewing/editing.
     */
    public ?array $selectedMarker = null;

    /**
     * Service reference.
     */
    protected FaceChartService $faceChartService;

    /**
     * Livewire event listeners.
     */
    protected $listeners = [
        'markerClicked' => 'onMarkerClicked',
        'faceClicked' => 'onFaceClicked',
        'markerMoved' => 'onMarkerMoved',
        'refreshMarkers' => 'loadMarkers',
        'setService' => 'setService',
        'setDirection' => 'onSetDirection',
        'setArrowEndpoint' => 'onSetArrowEndpoint',
    ];

    public function boot(FaceChartService $faceChartService): void
    {
        $this->faceChartService = $faceChartService;
    }

    public function mount(
        int $patientId,
        ?int $appointmentId = null,
        bool $editMode = false,
        ?int $serviceId = null,
        ?int $serviceCategoryId = null
    ): void {
        $this->patientId = $patientId;
        $this->appointmentId = $appointmentId;
        $this->isEditing = $editMode && $appointmentId !== null;

        // Load patient name
        $patient = Patient::find($patientId);
        $this->patientName = $patient?->full_name;

        // Set service context
        if ($serviceId) {
            $this->setService($serviceId);
        } elseif ($serviceCategoryId) {
            $this->serviceCategoryId = $serviceCategoryId;
            $category = \Modules\Services\Models\ServiceCategory::find($serviceCategoryId);
            if ($category) {
                $this->newMarker['color'] = $category->color ?? '#3B82F6';
            }
        }

        // Set default color based on most common marker type
        $this->newMarker['color'] = config('face_chart.marker_colors.injection', '#3B82F6');

        $this->loadMarkers();
    }

    /**
     * Load markers from database.
     */
    public function loadMarkers(): void
    {
        $this->markers = $this->faceChartService->getMarkersForVisualization(
            $this->patientId,
            $this->appointmentId,
            $this->dateFrom,
            $this->dateTo,
            $this->regionFilter
        );

        $this->dispatch('markersLoaded', markers: $this->markers);
    }

    /**
     * Toggle edit mode.
     */
    public function toggleEditMode(): void
    {
        if (!$this->appointmentId) {
            return; // Can only edit with appointment context
        }

        $this->isEditing = !$this->isEditing;
        $this->selectedMarkerId = null;
        $this->selectedMarker = null;

        $this->dispatch('editModeChanged', isEditing: $this->isEditing);
    }

    /**
     * Set the current service context.
     */
    public function setService(?int $serviceId): void
    {
        $this->serviceId = $serviceId;

        if ($serviceId) {
            $service = \Modules\Services\Models\Service::with('category')->find($serviceId);
            if ($service) {
                $this->serviceName = $service->translated_name;
                $this->serviceCategoryId = $service->category_id;

                // Set default color from category
                if ($service->category) {
                    $this->newMarker['color'] = $service->category->color ?? '#3B82F6';
                }
            }
        } else {
            $this->serviceName = null;
            $this->serviceCategoryId = null;
        }
    }

    /**
     * Handle click on 3D face - add new marker.
     *
     * @param float $x X coordinate
     * @param float $y Y coordinate
     * @param float $z Z coordinate
     * @param string|null $region Face region
     * @param array|null $formData Additional form data from modal
     */
    public function onFaceClicked(float $x, float $y, float $z, ?string $region = null, ?array $formData = null): void
    {
        if (!$this->isEditing || !$this->appointmentId) {
            return;
        }

        // Merge form data with defaults
        $data = [
            'patient_id' => $this->patientId,
            'appointment_id' => $this->appointmentId,
            'service_id' => $this->serviceId,
            'service_category_id' => $this->serviceCategoryId,
            'x' => $x,
            'y' => $y,
            'z' => $z,
            'face_region' => $region ?? $this->newMarker['face_region'],
            'marker_type' => $this->newMarker['marker_type'],
            'color' => $this->newMarker['color'],
            'unit_type' => $this->newMarker['unit_type'],
        ];

        // If form data provided from modal, use it
        if ($formData) {
            $data['product_name'] = $formData['product_name'] ?? null;
            $data['units'] = $formData['units'] ?? null;
            $data['unit_type'] = $formData['unit_type'] ?? 'units';
            $data['direction_x'] = $formData['direction_x'] ?? null;
            $data['direction_y'] = $formData['direction_y'] ?? null;
            $data['direction_z'] = $formData['direction_z'] ?? null;
            $data['depth_mm'] = $formData['depth_mm'] ?? null;
            $data['notes'] = $formData['notes'] ?? null;
        } else {
            // Use sidebar form data (legacy behavior)
            $data['product_name'] = $this->newMarker['product_name'] ?: null;
            $data['units'] = $this->newMarker['units'] ?: null;
            $data['direction_x'] = $this->newMarker['direction_x'];
            $data['direction_y'] = $this->newMarker['direction_y'];
            $data['direction_z'] = $this->newMarker['direction_z'];
            $data['depth_mm'] = $this->newMarker['depth_mm'];
            $data['notes'] = $this->newMarker['notes'] ?: null;
        }

        $marker = $this->faceChartService->createMarker($data);

        $this->loadMarkers();

        // Select the newly created marker
        $this->selectMarker($marker->id);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('face_chart::face_chart.messages.marker_added'),
        ]);
    }

    /**
     * Handle click on existing marker.
     */
    public function onMarkerClicked(int $markerId): void
    {
        $this->selectMarker($markerId);
    }

    /**
     * Select a marker for viewing/editing.
     */
    public function selectMarker(?int $markerId): void
    {
        $this->selectedMarkerId = $markerId;

        if ($markerId) {
            $marker = FaceChartMarker::with(['service', 'performedBy', 'appointment'])->find($markerId);
            if ($marker) {
                $this->selectedMarker = [
                    'id' => $marker->id,
                    'x' => (float) $marker->x,
                    'y' => (float) $marker->y,
                    'z' => (float) $marker->z,
                    'face_region' => $marker->face_region,
                    'marker_type' => $marker->marker_type,
                    'product_name' => $marker->product_name,
                    'units' => $marker->units,
                    'unit_type' => $marker->unit_type,
                    'color' => $marker->color,
                    'notes' => $marker->notes,
                    'performed_at' => $marker->performed_at?->format('Y-m-d'),
                    'performed_by_name' => $marker->performedBy?->name,
                    'service_name' => $marker->service?->name,
                    'appointment_code' => $marker->appointment?->code,
                    'is_editable' => $this->appointmentId && $marker->appointment_id === $this->appointmentId,
                ];
            }
        } else {
            $this->selectedMarker = null;
        }

        $this->dispatch('markerSelected', markerId: $markerId, marker: $this->selectedMarker);
    }

    /**
     * Update selected marker.
     */
    public function updateSelectedMarker(): void
    {
        if (!$this->selectedMarkerId || !$this->selectedMarker) {
            return;
        }

        // Check if editable
        $marker = FaceChartMarker::find($this->selectedMarkerId);
        if (!$marker || $marker->appointment_id !== $this->appointmentId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('face_chart::face_chart.messages.cannot_edit_historical'),
            ]);
            return;
        }

        $this->faceChartService->updateMarker($this->selectedMarkerId, [
            'face_region' => $this->selectedMarker['face_region'],
            'marker_type' => $this->selectedMarker['marker_type'],
            'product_name' => $this->selectedMarker['product_name'],
            'units' => $this->selectedMarker['units'],
            'unit_type' => $this->selectedMarker['unit_type'],
            'color' => $this->selectedMarker['color'],
            'notes' => $this->selectedMarker['notes'],
        ]);

        $this->loadMarkers();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('face_chart::face_chart.messages.marker_updated'),
        ]);
    }

    /**
     * Delete selected marker.
     */
    public function deleteSelectedMarker(): void
    {
        if (!$this->selectedMarkerId) {
            return;
        }

        // Check if editable
        $marker = FaceChartMarker::find($this->selectedMarkerId);
        if (!$marker || $marker->appointment_id !== $this->appointmentId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('face_chart::face_chart.messages.cannot_delete_historical'),
            ]);
            return;
        }

        $this->faceChartService->deleteMarker($this->selectedMarkerId);

        $this->selectedMarkerId = null;
        $this->selectedMarker = null;
        $this->loadMarkers();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('face_chart::face_chart.messages.marker_deleted'),
        ]);
    }

    /**
     * Get marker data for editing in modal.
     */
    public function getMarkerForEdit(int $markerId): void
    {
        $marker = FaceChartMarker::with(['service', 'performedBy', 'appointment'])->find($markerId);

        if (!$marker) {
            return;
        }

        // Debug: Log the appointment IDs for comparison
        \Log::info('[FaceChart] getMarkerForEdit', [
            'markerId' => $markerId,
            'marker_appointment_id' => $marker->appointment_id,
            'current_appointmentId' => $this->appointmentId,
            'types' => [
                'marker' => gettype($marker->appointment_id),
                'current' => gettype($this->appointmentId),
            ],
        ]);

        // Use non-strict comparison (==) to handle type differences
        $isEditable = $this->appointmentId && (int) $marker->appointment_id === (int) $this->appointmentId;

        $markerData = [
            'id' => $marker->id,
            'x' => (float) $marker->x,
            'y' => (float) $marker->y,
            'z' => (float) $marker->z,
            'face_region' => $marker->face_region,
            'marker_type' => $marker->marker_type,
            'product_name' => $marker->product_name,
            'units' => $marker->units,
            'unit_type' => $marker->unit_type,
            'color' => $marker->color,
            'notes' => $marker->notes,
            'direction_x' => $marker->direction_x,
            'direction_y' => $marker->direction_y,
            'direction_z' => $marker->direction_z,
            'depth_mm' => $marker->depth_mm,
            'performed_at' => $marker->performed_at?->format('Y-m-d'),
            'performed_by_name' => $marker->performedBy?->name,
            'service_name' => $marker->service?->name,
            'appointment_code' => $marker->appointment?->code,
            'is_editable' => $isEditable,
            // Debug info
            '_debug_marker_appt' => $marker->appointment_id,
            '_debug_current_appt' => $this->appointmentId,
        ];

        $this->dispatch('markerDataLoaded', marker: $markerData);
    }

    /**
     * Update marker from modal form data.
     */
    public function updateMarkerFromModal(int $markerId, array $formData): void
    {
        // Check if editable
        $marker = FaceChartMarker::find($markerId);
        if (!$marker || $marker->appointment_id !== $this->appointmentId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('face_chart::face_chart.messages.cannot_edit_historical'),
            ]);
            return;
        }

        $this->faceChartService->updateMarker($markerId, [
            'product_name' => $formData['product_name'] ?? null,
            'units' => $formData['units'] ?? null,
            'unit_type' => $formData['unit_type'] ?? 'units',
            'direction_x' => $formData['direction_x'] ?? null,
            'direction_y' => $formData['direction_y'] ?? null,
            'direction_z' => $formData['direction_z'] ?? null,
            'depth_mm' => $formData['depth_mm'] ?? null,
            'notes' => $formData['notes'] ?? null,
        ]);

        $this->loadMarkers();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('face_chart::face_chart.messages.marker_updated'),
        ]);
    }

    /**
     * Delete marker from modal.
     */
    public function deleteMarkerFromModal(int $markerId): void
    {
        // Check if editable
        $marker = FaceChartMarker::find($markerId);
        if (!$marker || $marker->appointment_id !== $this->appointmentId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('face_chart::face_chart.messages.cannot_delete_historical'),
            ]);
            return;
        }

        $this->faceChartService->deleteMarker($markerId);
        $this->loadMarkers();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('face_chart::face_chart.messages.marker_deleted'),
        ]);
    }

    /**
     * Handle marker drag-and-drop repositioning.
     */
    public function onMarkerMoved(int $markerId, float $x, float $y, float $z): void
    {
        // Check if editable
        $marker = FaceChartMarker::find($markerId);
        if (!$marker || $marker->appointment_id !== $this->appointmentId) {
            return;
        }

        $this->faceChartService->updateMarker($markerId, [
            'x' => $x,
            'y' => $y,
            'z' => $z,
        ]);

        $this->loadMarkers();
    }

    /**
     * Handle setting direction for a marker (legacy - direction vector).
     */
    public function onSetDirection(int $markerId, float $dirX, float $dirY, float $dirZ, ?float $depthMm = null): void
    {
        // Check if editable
        $marker = FaceChartMarker::find($markerId);
        if (!$marker || $marker->appointment_id !== $this->appointmentId) {
            return;
        }

        $this->faceChartService->updateMarker($markerId, [
            'direction_x' => $dirX,
            'direction_y' => $dirY,
            'direction_z' => $dirZ,
            'depth_mm' => $depthMm,
        ]);

        $this->loadMarkers();
    }

    /**
     * Handle setting arrow endpoint for surface-following arrows.
     */
    public function onSetArrowEndpoint(int $markerId, float $endX, float $endY, float $endZ): void
    {
        // Check if editable
        $marker = FaceChartMarker::find($markerId);
        if (!$marker || $marker->appointment_id !== $this->appointmentId) {
            return;
        }

        $this->faceChartService->updateMarker($markerId, [
            'arrow_end_x' => $endX,
            'arrow_end_y' => $endY,
            'arrow_end_z' => $endZ,
            // Clear old direction data
            'direction_x' => null,
            'direction_y' => null,
            'direction_z' => null,
            'depth_mm' => null,
        ]);

        $this->loadMarkers();
    }

    /**
     * Apply filters and reload markers.
     */
    public function applyFilters(): void
    {
        $this->loadMarkers();
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->regionFilter = null;
        $this->typeFilter = null;
        $this->loadMarkers();
    }

    /**
     * Update marker type preset color.
     */
    public function updatedNewMarkerMarkerType($value): void
    {
        $colors = config('face_chart.marker_colors', []);
        $this->newMarker['color'] = $colors[$value] ?? config('face_chart.default_marker_color', '#FF6B6B');
    }

    /**
     * Get region options for select.
     */
    public function getRegionOptionsProperty(): array
    {
        return $this->faceChartService->getRegionOptions();
    }

    /**
     * Get marker type options for select.
     */
    public function getMarkerTypeOptionsProperty(): array
    {
        return $this->faceChartService->getMarkerTypeOptions();
    }

    /**
     * Get unit type options for select.
     */
    public function getUnitTypeOptionsProperty(): array
    {
        return $this->faceChartService->getUnitTypeOptions();
    }

    /**
     * Get marker statistics.
     */
    public function getStatsProperty(): array
    {
        return $this->faceChartService->getPatientStats($this->patientId);
    }

    public function render()
    {
        return view('face_chart::livewire.face-chart-3d');
    }
}
