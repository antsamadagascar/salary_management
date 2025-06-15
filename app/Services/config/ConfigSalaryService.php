<?php

namespace App\Services\config;

use App\Services\api\ErpApiService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ConfigSalaryService
{
    protected ErpApiService $erpApiService;

    public function __construct(ErpApiService $erpApiService)
    {
        $this->erpApiService = $erpApiService;
    }

    public function getSalaryComponents()
    {
        try {
            return $this->erpApiService->getResource('Salary Component');
        } catch (\Exception $e) {
            Log::error("Erreur récupération components salariaux : " . $e->getMessage());
            return [];
        }
    }

    public function modifyBaseSalary(
        array $employees,
        string $salaryComponent,
        float $montant,
        string $condition,
        string $option,
        float $pourcentage,
        string $targetMonth = null 
    ): array {
        try {
            $modifiedEmployees = [];

            foreach ($employees as $employee) {
                if (!isset($employee['name'])) {
                    Log::error('Clé name manquante', ['employee' => $employee]);
                    continue;
                }

                $assignment = $this->getActiveSalaryAssignment($employee['name']);
                if (!$assignment) {
                    Log::info("Aucun assignment actif pour {$employee['name']}");
                    continue;
                }

                // Récupére la valeur du composant depuis le dernier salary slip
                $componentValue = $this->getComponentValueFromLatestSlip($employee['name'], $salaryComponent);
                
                if ($this->checkCondition($componentValue, $montant, $condition)) {
                    $currentBaseSalary = $assignment['base'] ?? 0;
                    $newBaseSalary = $this->calculateNewSalary($currentBaseSalary, $pourcentage, $option);

                    $actualTargetMonth = $targetMonth ?: Carbon::parse($assignment['from_date'])->format('Y-m');

                    $updateResult = $this->updateSalaryForExistingMonth(
                        $employee['name'], 
                        $assignment, 
                        $newBaseSalary, 
                        $actualTargetMonth
                    );
                    
                    if ($updateResult) {
                        $modifiedEmployees[] = [
                            'employee_name' => $employee['employee_name'] ?? $employee['name'],
                            'old_base_salary' => $currentBaseSalary,
                            'new_base_salary' => $newBaseSalary,
                            'target_month' => $actualTargetMonth,
                            'original_from_date' => $assignment['from_date']
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'modified_employees' => $modifiedEmployees,
                'message' => 'Salaires modifiés avec succès'
            ];
        } catch (\Exception $e) {
            Log::error("Erreur modification salaires : " . $e->getMessage());
            return [
                'success' => false,
                'modified_employees' => [],
                'message' => 'Erreur : ' . $e->getMessage()
            ];
        }
    }

    private function getActiveSalaryAssignment(string $employeeName): ?array
    {
        try {
            $filters = ['employee' => $employeeName, 'docstatus' => 1];
            $assignments = $this->erpApiService->getResource('Salary Structure Assignment', [
                'filters' => json_encode($filters),
                'fields' => json_encode(['name', 'employee', 'salary_structure', 'from_date', 'base', 'company', 'currency'])
            ]);

            if (empty($assignments)) {
                return null;
            }

            usort($assignments, fn($a, $b) => strtotime($b['from_date']) - strtotime($a['from_date']));
            return $assignments[0];
        } catch (\Exception $e) {
            Log::error("Erreur récupération assignments pour {$employeeName} : " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupére la valeur d'un composant depuis le dernier salary slip
     */
    private function getComponentValueFromLatestSlip(string $employeeName, string $componentName): float
    {
        try {
            // Récupére le dernier salary slip validé
            $slips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => json_encode([
                    ['employee', '=', $employeeName],
                    ['docstatus', '=', 1]
                ]),
                'fields' => json_encode(['name', 'earnings', 'deductions']),
                'order_by' => 'end_date desc',
                'limit' => 1
            ]);

            if (empty($slips)) {
                Log::info("Aucun salary slip trouvé pour {$employeeName}");
                return 0.0;
            }

            $slip = $slips[0];
            $allComponents = array_merge(
                $slip['earnings'] ?? [],
                $slip['deductions'] ?? []
            );

            foreach ($allComponents as $component) {
                if ($component['salary_component'] === $componentName) {
                    return (float) ($component['amount'] ?? 0);
                }
            }

            Log::info("Composant {$componentName} non trouvé dans le salary slip de {$employeeName}");
            return 0.0;

        } catch (\Exception $e) {
            Log::error("Erreur récupération composant {$componentName} pour {$employeeName}: " . $e->getMessage());
            return 0.0;
        }
    }

    private function checkCondition(float $componentValue, float $montant, string $condition): bool
    {
        return match ($condition) {
            'inferieur' => $componentValue < $montant,
            'superieur' => $componentValue > $montant,
            'egal' => abs($componentValue - $montant) < 0.01,
            'inferieur_egal' => $componentValue <= $montant,
            'superieur_egal' => $componentValue >= $montant,
            default => false
        };
    }

    private function calculateNewSalary(float $baseSalary, float $pourcentage, string $option): float
    {
        $multiplier = $option === 'augmentation' ? (1 + $pourcentage / 100) : (1 - $pourcentage / 100);
        return round($baseSalary * $multiplier, 2);
    }

    private function updateSalaryForExistingMonth(
        string $employeeName, 
        array $currentAssignment, 
        float $newBaseSalary, 
        string $targetMonth
    ): bool {
        try {
            $originalFromDate = $currentAssignment['from_date'];
            
            Log::info("Début mise à jour salary pour {$employeeName} - from_date ORIGINAL: {$originalFromDate}");

            $targetDate = Carbon::createFromFormat('Y-m', $targetMonth);
            $slipStartDate = $targetDate->copy()->startOfMonth()->format('Y-m-d');
            $slipEndDate = $targetDate->copy()->endOfMonth()->format('Y-m-d');
            
            // 1. Mettre à jour le Salary Structure Assignment
            $assignmentSuccess = $this->updateSalaryStructureAssignmentKeepDate(
                $employeeName, 
                $currentAssignment, 
                $newBaseSalary, 
                $originalFromDate
            );

            if (!$assignmentSuccess) {
                Log::error("Échec mise à jour Salary Structure Assignment pour {$employeeName}");
                return false;
            }

            // 2. Regénére le Salary Slip - L'ERP recalculera automatiquement tous les composants
            $slipSuccess = $this->regenerateSalarySlip(
                $employeeName,
                $currentAssignment['salary_structure'],
                $slipStartDate,
                $slipEndDate,
                $currentAssignment['company'] ?? 'Orinasa SA'
            );

            if (!$slipSuccess) {
                Log::error("Échec mise à jour Salary Slip pour {$employeeName}");
                return false;
            }

            Log::info("Mise à jour complète réussie pour {$employeeName}");
            return true;

        } catch (\Exception $e) {
            Log::error("Erreur mise à jour salary pour {$employeeName}: " . $e->getMessage());
            return false;
        }
    }

    private function updateSalaryStructureAssignmentKeepDate(
        string $employeeName, 
        array $currentAssignment, 
        float $newBaseSalary, 
        string $originalFromDate
    ): bool {
        try {
            Log::info("Mise à jour Salary Structure Assignment pour {$employeeName}");

            // Recherche et annule l'assignment existant
            $existingAssignments = $this->erpApiService->getResource('Salary Structure Assignment', [
                'filters' => json_encode([
                    ['employee', '=', $employeeName],
                    ['salary_structure', '=', $currentAssignment['salary_structure']],
                    ['from_date', '=', $originalFromDate],
                    ['docstatus', '!=', 2]
                ]),
                'fields' => json_encode(['name', 'base', 'docstatus', 'from_date'])
            ]);

            // Annule les assignments existants
            foreach ($existingAssignments as $assignment) {
                if ($assignment['docstatus'] == 1) {
                    $this->erpApiService->executeMethod('frappe.client', 'cancel', [
                        'doctype' => 'Salary Structure Assignment',
                        'name' => $assignment['name']
                    ]);
                    Log::info("Assignment {$assignment['name']} annulé");
                } elseif ($assignment['docstatus'] == 0) {
                    $this->erpApiService->deleteResource('Salary Structure Assignment', $assignment['name']);
                    Log::info("Assignment draft {$assignment['name']} supprimé");
                }
            }

            // Création du nouveau assignment
            $newAssignmentData = [
                'employee' => $employeeName,
                'salary_structure' => $currentAssignment['salary_structure'],
                'from_date' => $originalFromDate,
                'base' => $newBaseSalary,
                'company' => $currentAssignment['company'] ?? 'Orinasa SA',
                'currency' => $currentAssignment['currency'] ?? 'MGA',
                'docstatus' => 1
            ];
            
            $newAssignment = $this->erpApiService->createResource('Salary Structure Assignment', $newAssignmentData);

            if (!$newAssignment) {
                Log::error("Échec création nouvel assignment pour {$employeeName}");
                return false;
            }

            Log::info("Nouvel assignment créé avec succès pour {$employeeName}");
            return true;

        } catch (\Exception $e) {
            Log::error("Erreur mise à jour Salary Structure Assignment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Regénére le Salary Slip - L'ERP recalculera automatiquement tous les composants
     */
    private function regenerateSalarySlip(
        string $employeeName,
        string $salaryStructure,
        string $startDate,
        string $endDate,
        string $company
    ): bool {
        try {
            Log::info("Régénération Salary Slip pour {$employeeName}");

            // Supprime les anciens slips pour cette période
            $existingSlips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => json_encode([
                    ['employee', '=', $employeeName],
                    ['start_date', '=', $startDate],
                    ['end_date', '=', $endDate],
                    ['docstatus', '!=', 2]
                ]),
                'fields' => json_encode(['name', 'docstatus'])
            ]);

            foreach ($existingSlips as $slip) {
                if ($slip['docstatus'] == 1) {
                    $this->erpApiService->executeMethod('frappe.client', 'cancel', [
                        'doctype' => 'Salary Slip',
                        'name' => $slip['name']
                    ]);
                    Log::info("Salary slip {$slip['name']} annulé");
                } elseif ($slip['docstatus'] == 0) {
                    $this->erpApiService->deleteResource('Salary Slip', $slip['name']);
                    Log::info("Salary slip draft {$slip['name']} supprimé");
                }
            }

            // Crée un nouveau salary slip - L'ERP calculera automatiquement tous les composants
            $slipData = [
                'employee' => $employeeName,
                'salary_structure' => $salaryStructure,
                'company' => $company,
                'posting_date' => $endDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payroll_frequency' => 'Monthly',
                'docstatus' => 1
            ];

            $newSlip = $this->erpApiService->createResource('Salary Slip', $slipData);

            if (!$newSlip) {
                Log::error("Échec création nouveau salary slip pour {$employeeName}");
                return false;
            }

            Log::info("Salary slip régénéré avec succès pour {$employeeName} - L'ERP a recalculé tous les composants automatiquement");
            return true;

        } catch (\Exception $e) {
            Log::error("Erreur régénération salary slip: " . $e->getMessage());
            return false;
        }
    }

    public function previewSalaryModification(
        array $employees,
        string $salaryComponent,
        float $montant,
        string $condition,
        string $option,
        float $pourcentage
    ): array {
        $preview = [];

        foreach ($employees as $employee) {
            if (!isset($employee['name'])) {
                continue;
            }

            $assignment = $this->getActiveSalaryAssignment($employee['name']);
            if ($assignment) {
                $componentValue = $this->getComponentValueFromLatestSlip($employee['name'], $salaryComponent);
                if ($this->checkCondition($componentValue, $montant, $condition)) {
                    $currentBaseSalary = $assignment['base'] ?? 0;
                    $newBaseSalary = $this->calculateNewSalary($currentBaseSalary, $pourcentage, $option);
                    $preview[] = [
                        'employee_name' => $employee['employee_name'] ?? $employee['name'],
                        'current_base_salary' => $currentBaseSalary,
                        'new_base_salary' => $newBaseSalary,
                        'difference' => $newBaseSalary - $currentBaseSalary
                    ];
                }
            }
        }

        return $preview;
    }
}