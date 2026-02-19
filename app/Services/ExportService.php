<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ExportService
{
    public function exportToPdf(
        string $view,
        array $data,
        string $filename,
        string $orientation = 'portrait'
    ): \Illuminate\Http\Response {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', $orientation);

        return $pdf->download("{$filename}.pdf");
    }

    public function exportToExcel(
        string $exportClass,
        string $filename
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        return Excel::download(new $exportClass, "{$filename}.xlsx");
    }

    public function exportCollectionToCsv(
        Collection $collection,
        array $headers,
        string $filename
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        $callback = function () use ($collection, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($collection as $row) {
                fputcsv($file, is_array($row) ? $row : $row->toArray());
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }
}
