<?php

namespace Modules\FaceChart\Services;

use Modules\FaceChart\Models\FaceChartMarker;
use Modules\Patients\Models\Patient;
use Illuminate\Support\Collection;

class FaceChartService
{
    /**
     * Get all markers for a patient.
     */
    public function getPatientMarkers(
        int $patientId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $region = null,
        ?string $markerType = null
    ): Collection {
        return FaceChartMarker::query()
            ->forPatient($patientId)
            ->when($dateFrom || $dateTo, fn($q) => $q->inDateRange($dateFrom, $dateTo))
            ->when($region, fn($q) => $q->inRegion($region))
            ->when($markerType, fn($q) => $q->ofType($markerType))
            ->with(['service.category', 'serviceCategory', 'performedBy', 'appointment'])
            ->latestFirst()
            ->get();
    }

    /**
     * Get markers for a specific appointment.
     */
    public function getAppointmentMarkers(int $appointmentId): Collection
    {
        return FaceChartMarker::query()
            ->forAppointment($appointmentId)
            ->with(['service.category', 'serviceCategory', 'performedBy'])
            ->get();
    }

    /**
     * Get markers formatted for Three.js visualization.
     */
    public function getMarkersForVisualization(
        int $patientId,
        ?int $currentAppointmentId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $region = null
    ): array {
        $markers = $this->getPatientMarkers($patientId, $dateFrom, $dateTo, $region);

        return $markers->map(function (FaceChartMarker $marker) use ($currentAppointmentId) {
            $data = $marker->toMarkerData();
            // Current appointment markers are editable
            $data['isEditable'] = $currentAppointmentId && $marker->appointment_id === $currentAppointmentId;
            $data['isCurrent'] = $data['isEditable'];
            return $data;
        })->toArray();
    }

    /**
     * Create a new marker.
     */
    public function createMarker(array $data): FaceChartMarker
    {
        // Set default color based on marker type if not provided
        if (empty($data['color']) && !empty($data['marker_type'])) {
            $colors = config('face_chart.marker_colors', []);
            $data['color'] = $colors[$data['marker_type']] ?? config('face_chart.default_marker_color', '#FF6B6B');
        }

        // Set performed_at to today if not provided
        if (empty($data['performed_at'])) {
            $data['performed_at'] = now()->toDateString();
        }

        // Set performed_by to current user if not provided
        if (empty($data['performed_by']) && auth()->check()) {
            $data['performed_by'] = auth()->id();
        }

        return FaceChartMarker::create($data);
    }

    /**
     * Update an existing marker.
     */
    public function updateMarker(int $markerId, array $data): FaceChartMarker
    {
        $marker = FaceChartMarker::findOrFail($markerId);
        $marker->update($data);
        return $marker->fresh();
    }

    /**
     * Delete a marker.
     */
    public function deleteMarker(int $markerId): bool
    {
        $marker = FaceChartMarker::findOrFail($markerId);
        return $marker->delete();
    }

    /**
     * Get marker statistics for a patient.
     */
    public function getPatientStats(int $patientId): array
    {
        $markers = FaceChartMarker::forPatient($patientId)->get();

        return [
            'total_markers' => $markers->count(),
            'by_type' => $markers->groupBy('marker_type')->map->count()->toArray(),
            'by_region' => $markers->groupBy('face_region')->map->count()->toArray(),
            'total_units' => $markers->sum('units'),
            'appointments_count' => $markers->pluck('appointment_id')->unique()->filter()->count(),
            'date_range' => [
                'first' => $markers->min('performed_at'),
                'last' => $markers->max('performed_at'),
            ],
        ];
    }

    /**
     * Clone markers from one appointment to another (for treatment templates).
     */
    public function cloneMarkersFromAppointment(
        int $sourceAppointmentId,
        int $targetAppointmentId,
        int $patientId
    ): Collection {
        $sourceMarkers = $this->getAppointmentMarkers($sourceAppointmentId);

        $newMarkers = collect();
        foreach ($sourceMarkers as $marker) {
            $newData = $marker->toArray();
            unset($newData['id'], $newData['created_at'], $newData['updated_at']);
            $newData['appointment_id'] = $targetAppointmentId;
            $newData['patient_id'] = $patientId;
            $newData['performed_at'] = now()->toDateString();
            $newData['performed_by'] = auth()->id();

            $newMarkers->push($this->createMarker($newData));
        }

        return $newMarkers;
    }

    /**
     * Get available regions with labels.
     */
    public function getRegionOptions(): array
    {
        $regions = config('face_chart.face_regions', []);
        $locale = app()->getLocale();

        return collect($regions)->mapWithKeys(function ($labels, $key) use ($locale) {
            return [$key => $labels[$locale] ?? $labels['en'] ?? $key];
        })->toArray();
    }

    /**
     * Get marker type options with labels.
     */
    public function getMarkerTypeOptions(): array
    {
        $types = config('face_chart.marker_types', []);
        $locale = app()->getLocale();

        return collect($types)->mapWithKeys(function ($labels, $key) use ($locale) {
            return [$key => $labels[$locale] ?? $labels['en'] ?? $key];
        })->toArray();
    }

    /**
     * Get unit type options with labels.
     */
    public function getUnitTypeOptions(): array
    {
        $types = config('face_chart.unit_types', []);
        $locale = app()->getLocale();

        return collect($types)->mapWithKeys(function ($labels, $key) use ($locale) {
            return [$key => $labels[$locale] ?? $labels['en'] ?? $key];
        })->toArray();
    }
}
