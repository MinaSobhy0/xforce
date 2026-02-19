<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Patients Module API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'auth:sanctum'])->prefix('v1/patients')->name('api.patients.')->group(function () {
    // Patient CRUD
    Route::get('/', function () {
        return \Modules\Patients\Models\Patient::query()
            ->with(['medicalHistory'])
            ->paginate(request('per_page', 25));
    })->name('index');

    Route::get('/{patient}', function (\Modules\Patients\Models\Patient $patient) {
        return $patient->load(['medicalHistory', 'notes', 'photos', 'consentForms']);
    })->name('show');

    Route::post('/', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
        ]);

        $patient = \Modules\Patients\Models\Patient::create($validated);
        return response()->json($patient, 201);
    })->name('store');

    Route::put('/{patient}', function (\Illuminate\Http\Request $request, \Modules\Patients\Models\Patient $patient) {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
        ]);

        $patient->update($validated);
        return response()->json($patient);
    })->name('update');

    Route::delete('/{patient}', function (\Modules\Patients\Models\Patient $patient) {
        $patient->delete();
        return response()->json(null, 204);
    })->name('destroy');

    // Search endpoint
    Route::get('/search', function (\Illuminate\Http\Request $request) {
        $query = $request->get('q');
        return \Modules\Patients\Models\Patient::query()
            ->where('first_name', 'ilike', "%{$query}%")
            ->orWhere('last_name', 'ilike', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orWhere('email', 'ilike', "%{$query}%")
            ->orWhere('code', 'ilike', "%{$query}%")
            ->limit(20)
            ->get();
    })->name('search');
});
