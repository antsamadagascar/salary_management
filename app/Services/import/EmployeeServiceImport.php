<?php

namespace App\Services\import;

use App\Services\ErpApiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Exception as CsvException;
use Carbon\Carbon;

class EmployeeServiceImport
{
   // private const REQUIRED_FIELDS = ['Ref', 'Nom', 'Prenom', 'genre', 'Date embauche', 'date naissance', 'company'];

    protected ErpApiService $apiService;

    public function __construct(ErpApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function import(UploadedFile $file): array
    {
        $results = ['success' => 0, 'errors' => [], 'skipped' => 0];

        try {
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            $csv->setHeaderOffset(0);
            $lineNumber = 1;

            $records = iterator_to_array($csv->getRecords());

            $existingEmployees = $this->getExistingEmployees();

            $genres = array_unique(array_map(
                fn($r) => trim($r['genre'] ?? ''),
                $records
            ));

            foreach ($genres as $genre) {
                if ($genre && !$this->ensureGenderExists($genre)) {
                    $results['errors'][] = "Genre manquant : Impossible de créer le genre '{$genre}'";
                }
            }

            foreach ($records as $record) {
                $lineNumber++;
                try {
                    $validation = $this->validateEmployeeData($record, $lineNumber);
                    if (!$validation['valid']) {
                        $results['errors'][] = $validation['error'];
                        continue;
                    }

                    $employeeRef = trim($record['Ref']);

                    if (in_array($employeeRef, $existingEmployees)) {
                        $results['skipped']++;
                        $results['errors'][] = "Ligne {$lineNumber}: L'employé avec la référence '{$employeeRef}' existe déjà - ignoré";
                        continue;
                    }

                    $companyName = trim($record['company']);
                    if (!$this->ensureCompanyExists($companyName)) {
                        $results['errors'][] = "Ligne {$lineNumber}: Impossible de créer/trouver l'entreprise: {$companyName}";
                        continue;
                    }

                    $employeeData = $this->prepareEmployeeData($record);
                    $success = $this->apiService->createResource('Employee', $employeeData);

                    if ($success) {
                        $results['success']++;
                        $existingEmployees[] = $employeeRef;
                    } else {
                        $results['errors'][] = "Ligne {$lineNumber}: Échec de la création de l'employé {$record['Nom']} {$record['Prenom']}";
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Ligne {$lineNumber}: " . $e->getMessage();
                }
            }
        } catch (CsvException $e) {
            $results['errors'][] = "Erreur de lecture du fichier CSV: " . $e->getMessage();
        }

        return $results;
    }

    private function getExistingEmployees(): array
    {
        try {
            $employees = $this->apiService->getResource('Employee', [
                'limit_page_length' => 1000,
                'fields' => ['employee_number']
            ]);

            return array_values(array_filter(
                array_column($employees, 'employee_number'),
                fn($number) => !empty($number)
            ));
        } catch (\Exception $e) {
            Log::warning('Impossible de récupérer la liste des employés existants: ' . $e->getMessage());
            return [];
        }
    }

    private function ensureGenderExists(string $gender): bool
    {
        try {
            if ($this->apiService->resourceExists("Gender/{$gender}")) {
                return true;
            }

            return $this->apiService->createResource('Gender', [
                'gender' => $gender
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création du genre {$gender}: " . $e->getMessage());
            return false;
        }
    }

    private function ensureCompanyExists(string $companyName): bool
    {
        try {
            if ($this->apiService->resourceExists("Company/{$companyName}")) {
                return true;
            }

            $companyData = [
                'company_name' => $companyName,
                'default_currency' => 'MGA', //default currency
                'country' => 'Madagascar', //default country
                'default_holiday_list' => 'Global Holiday List 2025' //hoilday list default
            ];

            return $this->apiService->createResource('Company', $companyData);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création de l'entreprise {$companyName}: " . $e->getMessage());
            return false;
        }
    }

    private function validateEmployeeData(array $record, int $lineNumber): array
    {
        // foreach (self::REQUIRED_FIELDS as $field) {
        //     if (empty(trim($record[$field] ?? ''))) {
        //         return ['valid' => false, 'error' => "Ligne {$lineNumber}: Le champ '{$field}' est requis"];
        //     }
        // }

        try {
            Carbon::createFromFormat('d/m/Y', trim($record['Date embauche']));
            Carbon::createFromFormat('d/m/Y', trim($record['date naissance']));
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => "Ligne {$lineNumber}: Format de date invalide (attendu: jj/mm/aaaa)"];
        }
        return ['valid' => true];
    }

    private function prepareEmployeeData(array $record): array
    {
        return [
            'employee_number' => trim($record['Ref']),
            'first_name' => trim($record['Prenom']),
            'last_name' => trim($record['Nom']),
            'gender' => trim($record['genre']) === 'Masculin' ? 'Male' : 'Female',
            'date_of_joining' => Carbon::createFromFormat('d/m/Y', trim($record['Date embauche']))->format('Y-m-d'),
            'date_of_birth' => Carbon::createFromFormat('d/m/Y', trim($record['date naissance']))->format('Y-m-d'),
            'company' => trim($record['company'])
        ];
    }
}