<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Exception as CsvException;
use Carbon\Carbon;

class ImportService
{
    protected ErpApiService $apiService;

    public function __construct(ErpApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function previewFile(UploadedFile $file, string $type): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();
        $records = iterator_to_array($csv->getRecords());
        $preview = array_slice($records, 0, 5, true);

        return [
            'headers' => $headers,
            'data' => $preview,
            'total_rows' => count($records),
            'type' => $type,
        ];
    }

    private function getServiceForType(string $type): object
    {
        return match ($type) {
            'employees' => $this->companyEmployeeService,
            'salary_structure' => $this->salaryStructureService,
            'payroll' => $this->payrollService,
            default => throw new \InvalidArgumentException("Type de fichier inconnu: {$type}"),
        };
    }

    public function importFile(UploadedFile $file, string $type): array
    {
        try {
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            $csv->setHeaderOffset(0);
            $records = iterator_to_array($csv->getRecords());

            foreach ($records as $index => $record) {
                foreach ($record as $key => $value) {
                    if (stripos($key, 'date') !== false && !empty(trim($value))) {
                        try {
                            Carbon::createFromFormat('d/m/Y', trim($value));
                        } catch (\Exception $e) {
                            return [
                                'success' => 0,
                                'errors' => ["Ligne " . ($index + 2) . ": Champ '{$key}' avec date invalide '{$value}' (format attendu: jj/mm/aaaa)"],
                                'skipped' => 0
                            ];
                        }
                    }
                }
            }

            $service = $this->getServiceForType($type);
            return $service->import($file);
        } catch (\Exception $e) {
            return [
                'success' => 0,
                'errors' => ["Erreur lors du traitement du fichier: " . $e->getMessage()],
                'skipped' => 0
            ];
        }
    }

}