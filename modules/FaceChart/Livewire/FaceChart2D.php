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
    ];

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

        // Filter to only 2D markers
        $this->markers = array_values(array_filter($allMarkers, function ($marker) {
            return ($marker['viewType'] ?? '3d') === '2d';
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

        // Map 'type' to 'marker_type' for database
        $markerType = $data['type'] ?? 'marking';
        if ($markerType === 'marker') {
            $markerType = 'injection'; // Default marker type
        }

        $markerData = [
            'patient_id' => $this->patientId,
            'appointment_id' => $this->appointmentId,
            'view_type' => '2d',
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

        \Log::info('FaceChart2D: Creating marker', $markerData);

        $this->faceChartService->createMarker($markerData);
        $this->loadMarkers();
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
            'view_type' => '2d',
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
            'view_type' => '2d',
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
