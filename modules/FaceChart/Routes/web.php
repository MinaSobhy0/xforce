<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FaceChart Web Routes
|--------------------------------------------------------------------------
|
| Web routes are handled through Filament panels and Livewire components.
| This file is intentionally minimal.
|
*/

// Serve the 3D model file with proper caching headers
Route::get('/models/face_head.glb', function () {
    $path = public_path('models/face_head.glb');

    if (!file_exists($path)) {
        abort(404);
    }

    $maxAge = config('face_chart.model_cache_max_age', 31536000);

    return response()->file($path, [
        'Content-Type' => 'model/gltf-binary',
        'Cache-Control' => "public, max-age={$maxAge}, immutable",
    ]);
})->name('face_chart.model');
