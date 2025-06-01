<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Exception as CsvException;
use Carbon\Carbon;

class PayrollService
{
    private const REQUIRED_FIELDS = ['Mois', 'Ref Employe', 'Salaire Base', 'Salaire'];

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

            $existingPayrolls = $this->getExistingPayrolls();

            foreach ($csv->getRecords() as $record) {
                $lineNumber++;
                try {
                    $validation = $this->validatePayrollData($record, $lineNumber);
                    if (!$validation['valid']) {
                        $results['errors'][] = $validation['error'];
                        continue;
                    }

                    $employeeNumber = trim($record['Ref Employe']);
                    $month = trim($record['Mois']);

                    $employee = $this->findEmployeeByNumber($employeeNumber);
                    if (!$employee) {
                        $results['errors'][] = "Ligne {$lineNumber}: Employé non trouvé: {$employeeNumber}";
                        continue;
                    }

                    $employeeRef = $employee['name'];

                    $payrollKey = $this->generatePayrollKey($employeeRef, $month);
                    if (isset($existingPayrolls[$payrollKey])) {
                        $results['skipped']++;
                        $results['errors'][] = "Ligne {$lineNumber}: Fiche de paie pour l'employé '{$employeeRef}' du mois '{$month}' existe déjà - ignorée";
                        continue;
                    }

                    $salaryStructureRef = trim($record['Salaire']);
                    if (!$this->apiService->resourceExists("Salary Structure/{$salaryStructureRef}")) {
                        $results['errors'][] = "Ligne {$lineNumber}: Structure salariale non trouvée: {$salaryStructureRef}";
                        continue;
                    }

                    $companyName = isset($record['company']) && !empty(trim($record['company'])) 
                        ? trim($record['company']) 
                        : 'My Company';

                    // Vérifier et créer/assigner une Holiday List si nécessaire
                    $holidayListValidation = $this->ensureHolidayList($employee, $companyName);
                    if (!$holidayListValidation['success']) {
                        $results['errors'][] = "Ligne {$lineNumber}: {$holidayListValidation['error']}";
                        continue;
                    }

                    $payrollData = $this->preparePayrollData($record, $companyName, $employeeRef);

                    $success = $this->apiService->createResource('Salary Slip', $payrollData);

                    if ($success) {
                        $results['success']++;
                        $existingPayrolls[$payrollKey] = true;
                    } else {
                        $results['errors'][] = "Ligne {$lineNumber}: Échec de la création de la paie pour l'employé {$employeeRef}";
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

    private function companyExists(string $companyName): bool
    {
        try {
            $companies = $this->apiService->getResource('Company', [
                'filters' => [['name', '=', $companyName]],
                'limit_page_length' => 1
            ]);
            return !empty($companies);
        } catch (\Exception $e) {
            Log::warning("Erreur lors de la vérification de l'existence de l'entreprise {$companyName}: " . $e->getMessage());
            return false;
        }
    }

    private function ensureHolidayList(array $employee, string $companyName): array
    {
        // Vérifier si l'employé a déjà une Holiday List
        if (!empty($employee['default_holiday_list'])) {
            return ['success' => true];
        }

        // Vérifier si l'entreprise a une Holiday List par défaut
        $company = $this->getCompanyHolidayList($companyName);
        if ($company && !empty($company['default_holiday_list'])) {
            return ['success' => true];
        }

        // Rechercher une Holiday List existante ou en créer une
        $holidayList = $this->findOrCreateHolidayList($companyName);
        if (!$holidayList) {
            // Dernière tentative : utiliser n'importe quelle Holiday List du système
            try {
                $anyHolidayList = $this->apiService->getResource('Holiday List', ['limit_page_length' => 1]);
                if (!empty($anyHolidayList)) {
                    $holidayList = $anyHolidayList[0]['name'];
                    Log::info("Utilisation de la Holiday List par défaut du système: " . $holidayList);
                } else {
                    return [
                        'success' => false, 
                        'error' => "Aucune Holiday List disponible dans le système. Veuillez créer manuellement une Holiday List dans ERPNext."
                    ];
                }
            } catch (\Exception $e) {
                return [
                    'success' => false, 
                    'error' => "Impossible d'accéder aux Holiday Lists du système: " . $e->getMessage()
                ];
            }
        }

        // Assigner la Holiday List à l'employé si nécessaire
        if (empty($employee['default_holiday_list'])) {
            $updateSuccess = $this->assignHolidayListToEmployee($employee['name'], $holidayList);
            if (!$updateSuccess) {
                Log::warning("Impossible d'assigner la Holiday List à l'employé {$employee['name']}, mais on continue le traitement");
                // On ne fait pas échouer le processus, on continue avec la Holiday List trouvée
            }
        }

        return ['success' => true];
    }

    private function getCompanyHolidayList(string $companyName): ?array
    {
        try {
            $companies = $this->apiService->getResource('Company', [
                'filters' => [['name', '=', $companyName]],
                'limit_page_length' => 1
            ]);
            return $companies[0] ?? null;
        } catch (\Exception $e) {
            Log::warning("Erreur lors de la récupération de l'entreprise {$companyName}: " . $e->getMessage());
            return null;
        }
    }

    private function findOrCreateHolidayList(string $companyName): ?string
    {
        try {
            // 1. Rechercher une Holiday List existante pour cette entreprise
            $holidayLists = $this->apiService->getResource('Holiday List', [
                'filters' => [['company', '=', $companyName]],
                'limit_page_length' => 1
            ]);

            if (!empty($holidayLists)) {
                Log::info("Holiday List trouvée pour l'entreprise {$companyName}: " . $holidayLists[0]['name']);
                return $holidayLists[0]['name'];
            }

            // 2. Rechercher toutes les Holiday List existantes (sans filtre company)
            $allHolidayLists = $this->apiService->getResource('Holiday List', [
                'limit_page_length' => 100
            ]);

            if (!empty($allHolidayLists)) {
                // Utiliser la première Holiday List disponible
                Log::info("Utilisation de la Holiday List existante: " . $allHolidayLists[0]['name']);
                return $allHolidayLists[0]['name'];
            }

            // 3. Tenter de créer une Holiday List simple
            $currentYear = date('Y');
            $holidayListName = "Holiday List {$currentYear}";
            
            // Vérifier si cette Holiday List existe déjà
            $existingByName = $this->apiService->getResource('Holiday List', [
                'filters' => [['name', '=', $holidayListName]],
                'limit_page_length' => 1
            ]);

            if (!empty($existingByName)) {
                Log::info("Holiday List existante trouvée par nom: " . $holidayListName);
                return $holidayListName;
            }

            // Créer une nouvelle Holiday List avec les champs minimum requis
            $holidayListData = [
                'holiday_list_name' => $holidayListName,
                'from_date' => "{$currentYear}-01-01",
                'to_date' => "{$currentYear}-12-31"
            ];

            // Ajouter la company seulement si elle existe dans ERPNext
            if ($this->companyExists($companyName)) {
                $holidayListData['company'] = $companyName;
            }

            Log::info("Tentative de création de Holiday List avec les données: " . json_encode($holidayListData));
            
            $created = $this->apiService->createResource('Holiday List', $holidayListData);
            if ($created) {
                Log::info("Holiday List créée avec succès: " . $holidayListName);
                return $holidayListName;
            }

            Log::error("Échec de la création de Holiday List");
            return null;

        } catch (\Exception $e) {
            Log::error("Erreur lors de la création/recherche de Holiday List: " . $e->getMessage());
            return null;
        }
    }

    private function assignHolidayListToEmployee(string $employeeName, string $holidayListName): bool
    {
        try {
            $updateData = [
                'default_holiday_list' => $holidayListName
            ];

            return $this->apiService->updateResource("Employee/{$employeeName}", $updateData);
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'assignation de Holiday List à l'employé {$employeeName}: " . $e->getMessage());
            return false;
        }
    }

    private function getExistingPayrolls(): array
    {
        try {
            $payrolls = $this->apiService->getResource('Salary Slip', ['limit_page_length' => 2000]);
            $existing = [];
            foreach ($payrolls as $payroll) {
                $key = $this->generatePayrollKey($payroll['employee'], $payroll['payroll_period']);
                $existing[$key] = true;
            }
            return $existing;
        } catch (\Exception $e) {
            Log::warning('Impossible de récupérer la liste des fiches de paie existantes: ' . $e->getMessage());
            return [];
        }
    }

    private function generatePayrollKey(string $employeeRef, string $monthOrPeriod): string
    {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $monthOrPeriod)) {
            try {
                $date = Carbon::createFromFormat('d/m/Y', $monthOrPeriod);
                $period = $date->format('Y-m');
            } catch (\Exception $e) {
                $period = $monthOrPeriod;
            }
        } else {
            $period = $monthOrPeriod;
        }

        return $employeeRef . '_' . $period;
    }

    private function findEmployeeByNumber(string $employeeNumber): ?array
    {
        $employees = $this->apiService->getResource('Employee', [
            'filters' => [['employee_number', '=', $employeeNumber]],
            'limit_page_length' => 1
        ]);
        return $employees[0] ?? null;
    }

    private function getEmployeeHolidayList(string $employeeRef): ?string
    {
        $employees = $this->apiService->getResource('Employee', [
            'filters' => [['name', '=', $employeeRef]],
            'limit_page_length' => 1
        ]);
        return $employees[0]['default_holiday_list'] ?? null;
    }

    private function validatePayrollData(array $record, int $lineNumber): array
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty(trim($record[$field] ?? ''))) {
                return ['valid' => false, 'error' => "Ligne {$lineNumber}: Le champ '{$field}' est requis"];
            }
        }

        try {
            Carbon::createFromFormat('d/m/Y', trim($record['Mois']));
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => "Ligne {$lineNumber}: Format de date invalide pour le mois (attendu: jj/mm/aaaa)"];
        }

        if (!is_numeric($record['Salaire Base']) || $record['Salaire Base'] <= 0) {
            return ['valid' => false, 'error' => "Ligne {$lineNumber}: Salaire Base doit être un nombre positif"];
        }

        return ['valid' => true];
    }

    private function preparePayrollData(array $record, string $companyName, string $employeeRef): array
    {
        $payrollDate = Carbon::createFromFormat('d/m/Y', trim($record['Mois']));
        $holidayList = $this->getEmployeeHolidayList($employeeRef);

        $data = [
            'payroll_period' => $payrollDate->format('Y-m'),
            'start_date' => $payrollDate->copy()->startOfMonth()->format('Y-m-d'),
            'end_date' => $payrollDate->copy()->endOfMonth()->format('Y-m-d'),
            'salary_structure' => trim($record['Salaire']),
            'company' => $companyName,
            'base_salary' => (float) $record['Salaire Base'],
            'employee' => $employeeRef,
        ];

        if ($holidayList) {
            $data['employee_holiday_list'] = $holidayList;
        }

        return $data;
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
            'employees' => array_column($this->apiService->getResource('Employee', ['limit_page_length' => 1000]), 'employee_number'),
            'salary_structures' => array_column($this->apiService->getResource('Salary Structure'), 'name'),
        ];
    }
}