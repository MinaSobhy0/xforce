<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Download a backup file.
     */
    public function download(Request $request, Backup $backup): StreamedResponse
    {
        // Check if backup exists and is completed
        if ($backup->status !== 'completed') {
            abort(404, 'Backup not found or not completed');
        }

        if (!$backup->path || !Storage::disk($backup->disk)->exists($backup->path)) {
            abort(404, 'Backup file not found');
        }

        $filename = $backup->filename ?? basename($backup->path);

        return Storage::disk($backup->disk)->download($backup->path, $filename);
    }
}
