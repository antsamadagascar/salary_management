<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Exception as CsvException;
use Carbon\Carbon;

class CompanyEmployeeService
{
    private const REQUIRED_FIELDS = ['Ref', 'Nom', 'Prenom', 'genre', 'Date embauche', 'date naissance', 'company'];
    private const VALID_GENDERS = ['Masculin', 'Feminin'];

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

            $existingEmployees = $this->getExistingEmployees();

            foreach ($csv->getRecords() as $record) {
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

    public function checkDependencies(): array
    {
        return [
            'companies' => array_column($this->apiService->getResource('Company'), 'name'),
            'employees' => $this->getExistingEmployees(),
        ];
    }

    /**
     * Récupère la liste des références d'employés existants
     */
    private function getExistingEmployees(): array
    {
        try {
            $employees = $this->apiService->getResource('Employee', ['limit_page_length' => 1000]);
            return array_column($employees, 'employee_number');
        } catch (\Exception $e) {
            Log::warning('Impossible de récupérer la liste des employés existants: ' . $e->getMessage());
            return [];
        }
    }

    public function ensureDefaultCompany(): bool
    {
        try {
            if ($this->apiService->resourceExists('Company/My Company')) {
                return true;
            }

            if (!$this->ensureCurrencyExists('MGA')) {
                Log::warning("Impossible de créer la devise MGA, utilisation d'une devise par défaut");
            }

            $companyData = [
                'company_name' => 'My Company',
                'abbr' => 'MC',
                'default_currency' => $this->getCurrencyToUse(),
                'country' => 'Madagascar',
            ];

            $success = $this->apiService->createResource('Company', $companyData);
            Log::{$success ? 'info' : 'error'}('Entreprise par défaut "My Company" ' . ($success ? 'créée avec succès' : 'échec de la création'));
            return $success;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de l\'entreprise par défaut: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureCompanyExists(string $companyName): bool
    {
        try {
            if ($this->apiService->resourceExists("Company/{$companyName}")) {
                return true;
            }

            if (!$this->ensureCurrencyExists('MGA')) {
                Log::warning("Impossible de créer la devise MGA, utilisation d'USD par défaut");
            }

            $companyData = [
                'company_name' => $companyName,
                'abbr' => strtoupper(substr($companyName, 0, 3)),
                'default_currency' => $this->getCurrencyToUse(),
                'country' => 'Madagascar',
            ];

            return $this->apiService->createResource('Company', $companyData);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création de l'entreprise {$companyName}: " . $e->getMessage());
            return false;
        }
    }

    private function ensureCurrencyExists(string $currencyCode): bool
    {
        try {
            if ($this->apiService->resourceExists("Currency/{$currencyCode}")) {
                return true;
            }

            if ($currencyCode !== 'MGA') {
                return false;
            }

            $currencyData = [
                'currency_name' => 'Malagasy Ariary',
                'currency_symbol' => 'Ar',
                'fraction' => 'Iraimbilanja',
                'fraction_units' => 5,
                'number_format' => '#,##0.00',
                'smallest_currency_fraction_value' => 0.2,
            ];

            return $this->apiService->createResource('Currency', $currencyData);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création de la devise {$currencyCode}: " . $e->getMessage());
            return false;
        }
    }

    private function getCurrencyToUse(): string
    {
        $currencies = ['MGA', 'USD', 'EUR'];
        foreach ($currencies as $currency) {
            if ($this->apiService->resourceExists("Currency/{$currency}")) {
                return $currency;
            }
        }
        return 'USD';
    }

    private function validateEmployeeData(array $record, int $lineNumber): array
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty(trim($record[$field] ?? ''))) {
                return ['valid' => false, 'error' => "Ligne {$lineNumber}: Le champ '{$field}' est requis"];
            }
        }

        try {
            Carbon::createFromFormat('d/m/Y', trim($record['Date embauche']));
            Carbon::createFromFormat('d/m/Y', trim($record['date naissance']));
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => "Ligne {$lineNumber}: Format de date invalide (attendu: jj/mm/aaaa)"];
        }

        if (!in_array(trim($record['genre']), self::VALID_GENDERS)) {
            return ['valid' => false, 'error' => "Ligne {$lineNumber}: Genre invalide (Masculin ou Feminin attendu)"];
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
          //  'status' => 'Active',
        ];
    }
}