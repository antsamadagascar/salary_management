<?php

namespace App\Services\import;

use App\Services\ErpApiService;
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
        $results = ['success' => 0, 'errors' => [], 'skipped' => 0, 'assignments_created' => 0];
        
        try {
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            $csv->setHeaderOffset(0);
            $records = iterator_to_array($csv->getRecords());
            
            Log::info("Début de l'import avec " . count($records) . " lignes");
            
            // PHASE 1: Validation et préparation des données
            $validRecords = [];
            $lineNumber = 1;
            
            foreach ($records as $record) {
                $lineNumber++;
                $validation = $this->validatePayrollData($record, $lineNumber);
                if (!$validation['valid']) {
                    $results['errors'][] = $validation['error'];
                    continue;
                }
                
                $validRecords[] = [
                    'record' => $record,
                    'line' => $lineNumber,
                    'employeeNumber' => trim($record['Ref Employe']),
                    'month' => trim($record['Mois']),
                    'baseSalary' => (float) $record['Salaire Base'],
                    'salaryStructure' => trim($record['Salaire'])
                ];
            }
            
            if (empty($validRecords)) {
                $results['errors'][] = "Aucune ligne valide trouvée dans le fichier CSV";
                return $results;
            }
            
            Log::info("Lignes valides trouvées: " . count($validRecords));
            
            // PHASE 2: Création/vérification des employés et company
            $processedRecords = [];
            $companyName = 'My Company'; // Valeur par défaut
            
            if (!$this->ensureCompanyExists($companyName)) {
                $results['errors'][] = "Impossible de créer/trouver l'entreprise: {$companyName}";
                return $results;
            }
            
            // Chargement des fiches de paie existantes UNE SEULE FOIS au début
            $existingPayrolls = $this->getExistingPayrolls();
            Log::info("Fiches de paie existantes chargées: " . count($existingPayrolls));
            
            foreach ($validRecords as $validRecord) {
                $employee = $this->findOrCreateEmployee($validRecord['employeeNumber']);
                if (!$employee) {
                    $results['errors'][] = "Ligne {$validRecord['line']}: Employé non trouvé et impossible à créer: {$validRecord['employeeNumber']}";
                    continue;
                }
                
                // Vérifier que la structure salariale existe
                if (!$this->apiService->resourceExists("Salary Structure/{$validRecord['salaryStructure']}")) {
                    $results['errors'][] = "Ligne {$validRecord['line']}: Structure salariale non trouvée: {$validRecord['salaryStructure']}";
                    continue;
                }
                
                // VÉRIFICATION DES DOUBLONS ICI - AVANT LA CRÉATION
                $payrollKey = $this->generatePayrollKey($employee['name'], $validRecord['month']);
                if (isset($existingPayrolls[$payrollKey])) {
                    $results['skipped']++;
                    $results['errors'][] = "Ligne {$validRecord['line']}: Fiche de paie pour l'employé '{$employee['name']}' du mois '{$validRecord['month']}' existe déjà - ignorée";
                    continue;
                }
                
                $validRecord['employee'] = $employee;
                $validRecord['companyName'] = $companyName;
                $validRecord['payrollKey'] = $payrollKey;
                $processedRecords[] = $validRecord;
            }
            
            if (empty($processedRecords)) {
                $results['errors'][] = "Aucune ligne ne peut être traitée après vérification des employés et doublons";
                return $results;
            }
            
            Log::info("Lignes prêtes pour traitement: " . count($processedRecords));
            
            // PHASE 3: Traitement intégré - Assignment + Fiche de paie
            Log::info("=== PHASE 3: Création des Assignments et Fiches de paie ===");
            
            foreach ($processedRecords as $processedRecord) {
                // Création de l'assignment en premier
                $assignmentResult = $this->createSalaryAssignment(
                    $processedRecord['employee']['name'],
                    $processedRecord['salaryStructure'],
                    $processedRecord['baseSalary'],
                    $processedRecord['month'],
                    $processedRecord['companyName']
                );
                
                if (!$assignmentResult) {
                    $results['errors'][] = "Ligne {$processedRecord['line']}: Échec de la création du salary assignment pour l'employé {$processedRecord['employee']['name']}";
                    continue;
                }
                
                $results['assignments_created']++;
                Log::info("Assignment créé pour employé {$processedRecord['employee']['name']} - Mois: {$processedRecord['month']}");
                
                // Crée la fiche de paie immédiatement après
                $payrollData = $this->preparePayrollData(
                    $processedRecord['record'], 
                    $processedRecord['companyName'], 
                    $processedRecord['employee']['name']
                );
                
                $success = $this->apiService->createResource('Salary Slip', $payrollData);
                
                if ($success) {
                    $results['success']++;
                    // Ajouterà la liste des existants pour éviter les doublons dans le même batch
                    $existingPayrolls[$processedRecord['payrollKey']] = true;
                    Log::info("Fiche de paie créée avec succès pour l'employé {$processedRecord['employee']['name']} - Mois: {$processedRecord['month']}");
                } else {
                    $results['errors'][] = "Ligne {$processedRecord['line']}: Échec de la création de la fiche de paie pour l'employé {$processedRecord['employee']['name']} (assignment créé)";
                }
            }
            
        } catch (CsvException $e) {
            $results['errors'][] = "Erreur de lecture du fichier CSV: " . $e->getMessage();
        } catch (\Exception $e) {
            $results['errors'][] = "Erreur générale: " . $e->getMessage();
            Log::error("Erreur générale dans l'import: " . $e->getMessage());
        }
        
        Log::info("Résultats finaux: " . json_encode($results));
        return $results;
    }

    private function findOrCreateEmployee(string $employeeNumber): ?array
    {
        try {
            // D'abord on essaye de trouver l'employé existant
            $employee = $this->findEmployeeByNumber($employeeNumber);
            if ($employee) {
                Log::info("Employé trouvé: {$employee['name']} (numéro: {$employeeNumber})");
                return $employee;
            }

            // Si pas trouvé, on crée un nouvel employé
            Log::info("Création d'un nouvel employé avec le numéro: {$employeeNumber}");
            
            $employeeData = [
                'employee_name' => "Employee {$employeeNumber}",
                'employee_number' => $employeeNumber,
                'first_name' => "Employee",
                'last_name' => $employeeNumber,
                'company' => 'My Company',
                'status' => 'Active',
                'date_of_joining' => date('Y-m-d'),
            ];

            $createdEmployeeName = $this->apiService->createResource('Employee', $employeeData);
            
            if ($createdEmployeeName) {
                Log::info("Employé créé avec succès: {$createdEmployeeName}");
                // Récupére l'employé créé
                return $this->findEmployeeByNumber($employeeNumber);
            }

            Log::error("Échec de la création de l'employé: {$employeeNumber}");
            return null;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création/recherche de l'employé {$employeeNumber}: " . $e->getMessage());
            return null;
        }
    }

    private function createSalaryAssignment(string $employeeRef, string $salaryStructure, float $baseSalary, string $month, string $companyName): bool
    {
        try {
            $payrollDate = Carbon::createFromFormat('d/m/Y', $month);
            // Utilise le premier jour du mois pour l'assignment from_date
            $assignmentFromDate = $payrollDate->copy()->startOfMonth()->format('Y-m-d');
            
            Log::info("Création salary assignment - Employé: {$employeeRef}, Structure: {$salaryStructure}, From Date: {$assignmentFromDate}, Salaire: {$baseSalary}");
            
            // Vérifie si un assignment existe déjà pour cette période (même mois/année)
            $existingAssignments = $this->apiService->getResource('Salary Structure Assignment', [
                'filters' => [
                    ['employee', '=', $employeeRef],
                    ['salary_structure', '=', $salaryStructure],
                    ['from_date', '>=', $assignmentFromDate],
                    ['from_date', '<', $payrollDate->copy()->addMonth()->startOfMonth()->format('Y-m-d')]
                ],
                'limit_page_length' => 10
            ]);

            if (!empty($existingAssignments)) {
                Log::info("Assignment déjà existant pour cette période");
                
                $assignment = $existingAssignments[0];
                $updateData = [];
                
                if ($assignment['base'] != $baseSalary) {
                    $updateData['base'] = $baseSalary;
                }
                
                if ($assignment['docstatus'] == 0) {
                    $updateData['docstatus'] = 1;
                }
                
                if (!empty($updateData)) {
                    $updated = $this->apiService->updateResource("Salary Structure Assignment/{$assignment['name']}", $updateData);
                    Log::info("Assignment mis à jour: " . ($updated ? 'Succès' : 'Échec'));
                    return $updated;
                }
                
                return true; // Déjà correct
            }
            
            // Créer un nouvel assignment
            $assignmentData = [
                'employee' => $employeeRef,
                'salary_structure' => $salaryStructure,
                'company' => $companyName,
                'from_date' => $assignmentFromDate,
                'base' => $baseSalary,
                'docstatus' => 1,
            ];

            Log::info("Création nouvel assignment avec données: " . json_encode($assignmentData));

            $assignmentName = $this->apiService->createResource('Salary Structure Assignment', $assignmentData);
            
            if ($assignmentName) {
                Log::info("Assignment créé avec succès: {$assignmentName}");
                return true;
            } else {
                Log::error("Échec de la création de l'assignment");
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création du salary assignment pour {$employeeRef}: " . $e->getMessage());
            return false;
        }
    }

    private function ensureCompanyExists(string $companyName): bool
    {
        try {
            if ($this->apiService->resourceExists("Company/{$companyName}")) {
                Log::info("Company existe déjà: {$companyName}");
                return true;
            }

            Log::info("Création de la company: {$companyName}");
            $companyData = [
                'company_name' => $companyName,
                'abbr' => strtoupper(substr($companyName, 0, 3)),
                'default_currency' => 'MGA',
                'country' => 'Madagascar',
            ];

            $result = $this->apiService->createResource('Company', $companyData);
            Log::info("Company créée: " . ($result ? 'Succès' : 'Échec'));
            return $result;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création de l'entreprise {$companyName}: " . $e->getMessage());
            return false;
        }
    }

    private function getExistingPayrolls(): array
    {
        try {
            $payrolls = $this->apiService->getResource('Salary Slip', ['limit_page_length' => 2000]);
            $existing = [];
            foreach ($payrolls as $payroll) {
                // Utiliser start_date si disponible, sinon payroll_period
                if (!empty($payroll['start_date'])) {
                    $period = Carbon::parse($payroll['start_date'])->format('Y-m');
                } elseif (!empty($payroll['payroll_period'])) {
                    $period = $payroll['payroll_period'];
                } else {
                    continue; // Ignorer si pas de période identifiable
                }
                
                $key = $this->generatePayrollKey($payroll['employee'], $period);
                $existing[$key] = true;
            }
            Log::info("Fiches de paie existantes chargées: " . count($existing));
            return $existing;
        } catch (\Exception $e) {
            Log::warning('Impossible de récupérer la liste des fiches de paie existantes: ' . $e->getMessage());
            return [];
        }
    }

    private function generatePayrollKey(string $employeeRef, string $monthOrPeriod): string
    {
        // Normaliser la période au format Y-m
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $monthOrPeriod)) {
            try {
                $date = Carbon::createFromFormat('d/m/Y', $monthOrPeriod);
                $period = $date->format('Y-m');
            } catch (\Exception $e) {
                Log::warning("Format de date invalide pour generatePayrollKey: {$monthOrPeriod}");
                $period = $monthOrPeriod;
            }
        } elseif (preg_match('/^\d{4}-\d{2}$/', $monthOrPeriod)) {
            $period = $monthOrPeriod; // Déjà au bon format
        } else {
            $period = $monthOrPeriod; // Garder tel quel
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
        $startDate = $payrollDate->copy()->startOfMonth()->format('Y-m-d');
        $endDate = $payrollDate->copy()->endOfMonth()->format('Y-m-d');
        
        // Le posting_date doit être le dernier jour du mois de paie
        // pour  éviter les problèmes de validation dans ERPNext
        $postingDate = $payrollDate->copy()->endOfMonth()->format('Y-m-d');
        
        $data = [
            'posting_date' => $postingDate,
            'payroll_period' => $payrollDate->format('Y-m'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'salary_structure' => trim($record['Salaire']),
            'company' => $companyName,
            'employee' => $employeeRef,
            'payroll_frequency' => 'Monthly',
            'docstatus' =>1
        ];

        Log::info("Données fiche de paie préparées - Employé: {$employeeRef}, Période: {$payrollDate->format('Y-m')}, Posting Date: {$postingDate}");
        
        return $data;
    }

    public function checkDependencies(): array
    {
        try {
            $employees = $this->apiService->getResource('Employee', ['limit_page_length' => 1000]);
            $salaryStructures = $this->apiService->getResource('Salary Structure', ['limit_page_length' => 100]);
            
            return [
                'employees' => array_column($employees, 'employee_number'),
                'salary_structures' => array_column($salaryStructures, 'name'),
            ];
        } catch (\Exception $e) {
            Log::error("Erreur lors de la vérification des dépendances: " . $e->getMessage());
            return [
                'employees' => [],
                'salary_structures' => [],
            ];
        }
    }
}