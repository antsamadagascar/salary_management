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
            // Stratégie 1: Récupére depuis le dernier salary slip validé
            $slipValue = $this->getComponentFromSlip($employeeName, $componentName);
            if ($slipValue !== null) {
                Log::debug("Valeur trouvée dans salary slip pour {$employeeName}: {$slipValue}");
                return $slipValue;
            }

            $structureValue = $this->getComponentFromStructure($employeeName, $componentName);
            if ($structureValue !== null) {
                Log::debug("Valeur calculée depuis structure pour {$employeeName}: {$structureValue}");
                return $structureValue;
            }

            Log::info("Composant {$componentName} non trouvé pour {$employeeName}");
            return 0.0;

        } catch (\Exception $e) {
            Log::error("Erreur récupération composant {$componentName} pour {$employeeName}: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Récupére la valeur depuis le salary slip
     */
    private function getComponentFromSlip(string $employeeName, string $componentName): ?float
    {
        try {
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
                return null;
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

            return null;
        } catch (\Exception $e) {
            Log::error("Erreur récupération depuis slip: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calcule la valeur depuis la salary structure (fallback)
     */
    private function getComponentFromStructure(string $employeeName, string $componentName): ?float
    {
        try {
            $assignment = $this->getActiveSalaryAssignment($employeeName);
            if (!$assignment || !isset($assignment['salary_structure'])) {
                return null;
            }

            $structure = $this->erpApiService->getResource("Salary Structure/{$assignment['salary_structure']}", [
                'fields' => json_encode(['earnings', 'deductions'])
            ]);

            $components = array_merge(
                $structure['earnings'] ?? [],
                $structure['deductions'] ?? []
            );

            foreach ($components as $component) {
                if ($component['salary_component'] === $componentName) {
                    // Si c'est un montant fixe
                    if (isset($component['amount']) && !empty($component['amount']) && empty($component['amount_based_on_formula'])) {
                        return (float) $component['amount'];
                    }

                    // Si c'est basé sur le salaire de base (formule simple)
                    if (!empty($component['formula'])) {
                        $baseSalary = (float) ($assignment['base'] ?? 0);
                        return $this->calculateSimpleFormula($component['formula'], $baseSalary);
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Erreur récupération depuis structure: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculer les formules simples uniquement (pour éviter la complexité)
     */
    private function calculateSimpleFormula(string $formula, float $baseSalary): float
    {
        try {
            $formula = str_replace(['base', 'SB', 'BS'], (string) $baseSalary, $formula);
            
            // Vérifie si la formule est simple (seulement chiffres et opérateurs de base)
            if (preg_match('/^[\d\.\+\-\*\/\(\)\s]+$/', $formula)) {
                $result = eval("return $formula;");
                return is_numeric($result) ? (float) $result : 0.0;
            }

            // Si la formule est complexe, retourner 0 (sera pris en charge par l'ERP)
            Log::info("Formule complexe ignorée: {$formula}");
            return 0.0;

        } catch (\Exception $e) {
            Log::error("Erreur calcul formule simple: " . $e->getMessage());
            return 0.0;
        }
    }

    private function checkCondition(float $componentValue, float $montant, string $condition): bool
    {
        $tolerance = 0.1; 
        
        $result = match ($condition) {
            'inferieur' => $componentValue < $montant,
            'superieur' => $componentValue > $montant,
            'egal' => abs($componentValue - $montant) <= $tolerance, 
            'inferieur_egal' => $componentValue <= ($montant + $tolerance),
            'superieur_egal' => $componentValue >= ($montant - $tolerance),
            default => false
        };

        Log::debug("Vérification condition", [
            'component_value' => $componentValue,
            'montant' => $montant,
            'condition' => $condition,
            'result' => $result
        ]);

        return $result;
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