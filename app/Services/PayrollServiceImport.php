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

                    // CORRECTION: Mettre à jour l'assignment avec le salaire de base AVANT de créer la fiche de paie
                    $assignmentResult = $this->updateSalaryAssignment($employeeRef, $salaryStructureRef, $baseSalary, $month);
                    if (!$assignmentResult) {
                        $results['errors'][] = "Ligne {$lineNumber}: Échec de la mise à jour du salary assignment pour l'employé {$employeeRef}";
                        continue;
                    }

                    $companyName = isset($record['company']) && !empty(trim($record['company'])) 
                        ? trim($record['company']) 
                        : 'My Company';

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

    /**
     * MÉTHODE CORRIGÉE: Met à jour le salary assignment avec le bon salaire de base
     */
    private function updateSalaryAssignment(string $employeeRef, string $salaryStructure, float $baseSalary, string $month): bool
    {
        try {
            $payrollDate = Carbon::createFromFormat('d/m/Y', $month);
            $payrollDateStr = $payrollDate->format('Y-m-d');
            
            // Chercher l'assignment existant pour cet employé et cette structure
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
                
                // Vérifier si l'assignment couvre la période de paie
                if ($fromDate <= $payrollDateStr && (empty($toDate) || $toDate >= $payrollDateStr)) {
                    $validAssignment = $assignment;
                    break;
                }
            }

            if ($validAssignment) {
                // Mettre à jour l'assignment existant avec le nouveau salaire de base
                $updateData = [
                    'base' => $baseSalary
                ];
                
                // Si l'assignment n'est pas encore soumis, le soumettre
                if ($validAssignment['docstatus'] == 0) {
                    $updateData['docstatus'] = 1;
                }
                
                $updated = $this->apiService->updateResource("Salary Structure Assignment/{$validAssignment['name']}", $updateData);
                
                if ($updated) {
                    Log::info("Assignment mis à jour: {$validAssignment['name']} pour employé {$employeeRef}");
                    return true;
                } else {
                    Log::error("Échec de la mise à jour de l'assignment: {$validAssignment['name']}");
                    return false;
                }
            } else {
                // Créer un nouvel assignment pour cette période
                $assignmentData = [
                    'employee' => $employeeRef,
                    'salary_structure' => $salaryStructure,
                    'from_date' => $payrollDateStr,
                    'base' => $baseSalary,
                    'docstatus' => 1, // Directement soumis
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
            Log::error("Erreur lors de la mise à jour du salary assignment pour {$employeeRef}: " . $e->getMessage());
            return false;
        }
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