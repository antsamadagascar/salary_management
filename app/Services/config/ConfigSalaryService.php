<?php

namespace App\Services\config;

use App\Services\api\ErpApiService;
use Illuminate\Support\Facades\Log;

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

    /**
     * Modifier les salaires selon une condition
     */
    public function modifyBaseSalary(
        array $employees,
        string $salaryComponent,
        float $montant,
        string $condition,
        string $option,
        float $pourcentage
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

                $componentValue = $this->getComponentValue($assignment, $salaryComponent);
                if ($this->checkCondition($componentValue, $montant, $condition)) {
                    $currentBaseSalary = $assignment['base'] ?? 0;
                    $newBaseSalary = $this->calculateNewSalary($currentBaseSalary, $pourcentage, $option);

                    $updateResult = $this->updateSalaryAssignment($employee['name'], $assignment, $newBaseSalary);
                    if ($updateResult) {
                        $modifiedEmployees[] = [
                            'employee_name' => $employee['employee_name'] ?? $employee['name'],
                            'old_base_salary' => $currentBaseSalary,
                            'new_base_salary' => $newBaseSalary
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

    /**
     * Récupérer l'assignment actif le plus récent
     */
    private function getActiveSalaryAssignment(string $employeeName): ?array
    {
        try {
            $filters = ['employee' => $employeeName, 'docstatus' => 1];
            $assignments = $this->erpApiService->getResource('Salary Structure Assignment', [
                'filters' => json_encode($filters),
                'fields' => json_encode(['name', 'employee', 'salary_structure', 'from_date', 'base'])
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

    private function getComponentValue(array $assignment, string $componentName): float
    {
        try {
            if (!isset($assignment['salary_structure'])) {
                return 0.0;
            }
    
            $baseSalary = (float) ($assignment['base'] ?? 0);
    
            $structure = $this->erpApiService->getResource("Salary Structure/{$assignment['salary_structure']}", [
                'fields' => json_encode(['earnings', 'deductions'])
            ]);
    
            $components = array_merge(
                $structure['earnings'] ?? [],
                $structure['deductions'] ?? []
            );
    
            foreach ($components as $component) {
                if ($component['salary_component'] === $componentName) {
                    if (isset($component['amount']) && empty($component['amount_based_on_formula'])) {
                        return (float) $component['amount'];
                    }
    
                    if (!empty($component['formula'])) {
                        $formula = $component['formula'];
    
                        $variables = [
                            'base' => $baseSalary,
                        ];
    
                        return $this->safeEvaluateFormula($formula, $vet mettre à jour les Salary Slipsariables);
                    }
                }
            }
    
            return 0.0;
        } catch (\Exception $e) {
            Log::error("Erreur récupération composant {$componentName} : " . $e->getMessage());
            return 0.0;
        }
    }
    

    /**
     * Vérifier la condition
     */
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

    private function safeEvaluateFormula(string $formula, array $vars): float
    {
        try {
            foreach ($vars as $key => $value) {
                $formula = str_replace($key, (string)(float) $value, $formula);
            }

            $result = eval("return $formula;");
            return is_numeric($result) ? (float)$result : 0.0;
        } catch (\Throwable $e) {
            Log::error("Erreur d'évaluation formule [$formula] : " . $e->getMessage());
            return 0.0;
        }
    }


    /**
     * Calculer le nouveau salaire
     */
    private function calculateNewSalary(float $baseSalary, float $pourcentage, string $option): float
    {
        $multiplier = $option === 'augmentation' ? (1 + $pourcentage / 100) : (1 - $pourcentage / 100);
        return round($baseSalary * $multiplier, 2);
    }

    /**
     * Annuler l'assignment actuel, créer un nouveau 
     */
    private function updateSalaryAssignment(string $employeeName, array $currentAssignment, float $newBaseSalary): bool
    {
        try {
            // Étape 1 : Annuler l'assignation actuelle
            $cancelResult = $this->erpApiService->executeMethod('frappe.client', 'cancel', [
                'doctype' => 'Salary Structure Assignment',
                'name' => $currentAssignment['name']
            ]);

            if (!$cancelResult || (isset($cancelResult['message']) && $cancelResult['message'] === false)) {
                Log::error("Échec annulation assignment {$currentAssignment['name']}", [
                    'response' => $cancelResult
                ]);
                return false;
            }

            // Étape 2 : Créer une nouvelle assignation
            $newAssignmentData = [
                'employee' => $employeeName,
                'salary_structure' => $currentAssignment['salary_structure'],
                'from_date' => date('Y-m-d'),
                'base' => $newBaseSalary,
                'company' => $currentAssignment['company'] ?? 'Orinasa SA',
                'currency' => $currentAssignment['currency'] ?? 'MGA',

                'docstatus' => 1

                'docstatus' => 0 // Créer en brouillon pour éviter les erreurs de soumission

            ];

            Log::debug("Données nouvelle assignation", ['data' => $newAssignmentData]);
            $newAssignment = $this->erpApiService->createResource('Salary Structure Assignment', $newAssignmentData);

            if (!$newAssignment || !isset($newAssignment['name'])) {
                Log::error("Échec création nouvel assignment pour {$employeeName}", [
                    'response' => $newAssignment
                ]);
                return false;
            }

            // Étape 3 : Soumettre la nouvelle assignation
            $submitResult = $this->erpApiService->executeMethod('frappe.client', 'submit', [
                'doctype' => 'Salary Structure Assignment',
                'name' => $newAssignment['name']
            ]);

            if (!$submitResult || (isset($submitResult['message']) && $submitResult['message'] === false)) {
                Log::error("Échec soumission nouvel assignment {$newAssignment['name']}", [
                    'response' => $submitResult
                ]);
                return false;
            }




        } catch (\Exception $e) {
            Log::error("Erreur mise à jour assignment pour {$employeeName}", [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'response' => method_exists($e, 'getResponse') ? $e->getResponse()->getBody()->getContents() : null

            ]);
            return false;
        }
    }
    
            ]);
            return false;
        }
    }

    /**
     * Aperçu des modifications
     */
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
                $componentValue = $this->getComponentValue($assignment, $salaryComponent);
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