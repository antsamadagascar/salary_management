<?php

namespace App\Services\export;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class ExportService
{
    /**
     * Export générique des données vers PDF
     */
    public function exportToPdf(string $view, array $data, string $filename = 'document.pdf'): \Illuminate\Http\Response
    {
        $pdf = Pdf::loadView($view, $data);
        
        // Configuration du PDF
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        return $pdf->download($filename);
    }

    /**
     * Export générique des données vers Excel/CSV
     */
    public function exportToExcel(array $data, array $headers, string $filename = 'export.csv'): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        // Pour cette version, on retourne un CSV
        $csvContent = $this->arrayToCsv($data, $headers);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($tempFile, $csvContent);
        
        return response()->download($tempFile, $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }

    /**
     * Convertir un array en CSV
     */
    private function arrayToCsv(array $data, array $headers): string
    {
        $output = fopen('php://temp', 'r+');
        
        // Ajoute des en-têtes
        fputcsv($output, $headers);
        
        // Ajoute des données
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}