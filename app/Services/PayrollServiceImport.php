<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Exception as CsvException;
use Carbon\Carbon;

class PayrollServiceImport
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
                    $baseSalary = (float) $record['Salaire Base'];

                    $employee = $this->findEmployeeByNumber($employeeNumber);
                    if (!$employee) {
                        $results['errors'][] = "Ligne {$lineNumber}: Employé non trouvé: {$employeeNumber}";
                        continue;
                    }

                    $employeeRef = $employee['name'];

                    // CORRECTION 4: Utiliser le bon format de clé pour vérifier les doublons
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

                    // CORRECTION 5: S'assurer que Company existe
                    $companyName = isset($record['company']) && !empty(trim($record['company'])) 
                        ? trim($record['company']) 
                        : 'My Company';
                    
                    if (!$this->ensureCompanyExists($companyName)) {
                        $results['errors'][] = "Ligne {$lineNumber}: Impossible de créer/trouver l'entreprise: {$companyName}";
                        continue;
                    }

                    // Créer/Mettre à jour le Salary Structure Assignment
                    $assignmentResult = $this->ensureSalaryAssignment($employeeRef, $salaryStructureRef, $baseSalary, $month, $companyName);
                    if (!$assignmentResult) {
                        $results['errors'][] = "Ligne {$lineNumber}: Échec de la création/mise à jour du salary assignment pour l'employé {$employeeRef}";
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
                    Log::error("Erreur ligne {$lineNumber}: " . $e->getMessage());
                }
            }
        } catch (CsvException $e) {
            $results['errors'][] = "Erreur de lecture du fichier CSV: " . $e->getMessage();
        }

        return $results;
    }

    // CORRECTION 6: Ajouter la méthode pour s'assurer que Company existe
    private function ensureCompanyExists(string $companyName): bool
    {
        try {
            if ($this->apiService->resourceExists("Company/{$companyName}")) {
                return true;
            }

            $companyData = [
                'company_name' => $companyName,
                'abbr' => strtoupper(substr($companyName, 0, 3)),
                'default_currency' => 'MGA',
                'country' => 'Madagascar',
            ];

            return $this->apiService->createResource('Company', $companyData);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création de l'entreprise {$companyName}: " . $e->getMessage());
            return false;
        }
    }

    // CORRECTION 7: Renommer et améliorer la méthode pour gérer les Salary Structure Assignment
    private function ensureSalaryAssignment(string $employeeRef, string $salaryStructure, float $baseSalary, string $month, string $companyName): bool
    {
        try {
            $payrollDate = Carbon::createFromFormat('d/m/Y', $month);
            $payrollDateStr = $payrollDate->format('Y-m-d');
            
            // Chercher les assignments existants pour cet employé
            $assignments = $this->apiService->getResource('Salary Structure Assignment', [
                'filters' => [
                    ['employee', '=', $employeeRef],
                    ['salary_structure', '=', $salaryStructure]
                ],
                'limit_page_length' => 50
            ]);

            $validAssignment = null;
            
            // Chercher un assignment valide pour cette période
            foreach ($assignments as $assignment) {
                $fromDate = $assignment['from_date'];
                $toDate = $assignment['to_date'] ?? null;
                
                if ($fromDate <= $payrollDateStr && (empty($toDate) || $toDate >= $payrollDateStr)) {
                    $validAssignment = $assignment;
                    break;
                }
            }

            if ($validAssignment) {
                // Mettre à jour l'assignment existant si nécessaire
                $updateData = [];
                
                if ($validAssignment['base'] != $baseSalary) {
                    $updateData['base'] = $baseSalary;
                }
                
                if ($validAssignment['docstatus'] == 0) {
                    $updateData['docstatus'] = 1;
                }
                
                if (!empty($updateData)) {
                    $updated = $this->apiService->updateResource("Salary Structure Assignment/{$validAssignment['name']}", $updateData);
                    
                    if ($updated) {
                        Log::info("Assignment mis à jour: {$validAssignment['name']} pour employé {$employeeRef}");
                        return true;
                    } else {
                        Log::error("Échec de la mise à jour de l'assignment: {$validAssignment['name']}");
                        return false;
                    }
                } else {
                    // Assignment déjà OK
                    return true;
                }
            } else {
                // Créer un nouvel assignment
                $assignmentData = [
                    'employee' => $employeeRef,
                    'salary_structure' => $salaryStructure,
                    'company' => $companyName,
                    'from_date' => $payrollDateStr,
                    'base' => $baseSalary,
                    'docstatus' => 1,
                ];

                $assignmentName = $this->apiService->createResource('Salary Structure Assignment', $assignmentData);
                
                if ($assignmentName) {
                    Log::info("Nouvel assignment créé: {$assignmentName} pour employé {$employeeRef}");
                    return true;
                } else {
                    Log::error("Échec de la création de l'assignment pour employé {$employeeRef}");
                    return false;
                }
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors de la gestion du salary assignment pour {$employeeRef}: " . $e->getMessage());
            return false;
        }
    }

    private function getExistingPayrolls(): array
    {
        try {
            $payrolls = $this->apiService->getResource('Salary Slip', ['limit_page_length' => 2000]);
            $existing = [];
            foreach ($payrolls as $payroll) {
                // CORRECTION 8: Utiliser le bon champ pour la période
                $period = $payroll['start_date'] ? Carbon::parse($payroll['start_date'])->format('Y-m') : $payroll['payroll_period'];
                $key = $this->generatePayrollKey($payroll['employee'], $period);
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
        try {
            $employees = $this->apiService->getResource('Employee', [
                'filters' => [['employee_number', '=', $employeeNumber]],
                'limit_page_length' => 1
            ]);
            return $employees[0] ?? null;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la recherche de l'employé {$employeeNumber}: " . $e->getMessage());
            return null;
        }
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
        
        $data = [
            'payroll_period' => $payrollDate->format('Y-m'),
            'start_date' => $payrollDate->copy()->startOfMonth()->format('Y-m-d'),
            'end_date' => $payrollDate->copy()->endOfMonth()->format('Y-m-d'),
            'salary_structure' => trim($record['Salaire']),
            'company' => $companyName,
            'employee' => $employeeRef,
            'payroll_frequency' => 'Monthly'
        ];

        return $data;
    }

    public function checkDependencies(): array
    {
        return [
            'employees' => array_column($this->apiService->getResource('Employee', ['limit_page_length' => 1000]), 'employee_number'),
            'salary_structures' => array_column($this->apiService->getResource('Salary Structure'), 'name'),
        ];
    }
}