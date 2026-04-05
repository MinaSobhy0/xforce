<?php

use Illuminate\Support\Facades\Route;
use Modules\FaceChart\Models\FaceChartMarker;

/*
|--------------------------------------------------------------------------
| FaceChart API Routes
|--------------------------------------------------------------------------
|
| API routes for face chart markers. Most operations are handled through
| Livewire, but these routes provide RESTful access for potential
| external integrations.
|
*/

Route::middleware(['auth:sanctum'])->prefix('face-chart')->group(function () {

    // Get markers for a patient
    Route::get('/patients/{patient}/markers', function ($patientId) {
        $markers = FaceChartMarker::forPatient($patientId)
            ->with(['service', 'performedBy'])
            ->latestFirst()
            ->get();

        return response()->json([
            'data' => $markers->map->toMarkerData(),
        ]);
    })->name('api.face_chart.markers.index');

    // Get markers for an appointment
    Route::get('/appointments/{appointment}/markers', function ($appointmentId) {
        $markers = FaceChartMarker::forAppointment($appointmentId)
            ->with(['service', 'performedBy'])
            ->get();

        return response()->json([
            'data' => $markers->map->toMarkerData(),
        ]);
    })->name('api.face_chart.markers.by_appointment');

});
