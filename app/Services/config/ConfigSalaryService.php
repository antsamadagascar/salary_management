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

    /**
     * Récupérer la valeur d'un composant salarial depuis la Salary Structure
     */
    private function getComponentValue(array $assignment, string $componentName): float
    {
        try {
            if (!isset($assignment['salary_structure'])) {
                return 0.0;
            }

            $baseSalary = (float) ($assignment['base'] ?? 0);
            $salaryStructure = $this->erpApiService->getResource("Salary Structure/{$assignment['salary_structure']}", [
                'fields' => json_encode(['name', 'earnings', 'deductions'])
            ]);

            $components = array_merge(
                $salaryStructure['earnings'] ?? [],
                $salaryStructure['deductions'] ?? []
            );

            $values = ['SB' => $baseSalary, 'IDM' => 0, 'TSP' => 0, 'IMP' => 0];

            foreach ($components as $item) {
                if ($item['salary_component'] === 'Salaire Base') {
                    $values['SB'] = $baseSalary;
                } elseif ($item['salary_component'] === $componentName) {
                    if (isset($item['amount']) && !$item['amount_based_on_formula']) {
                        return (float) $item['amount'];
                    } elseif (isset($item['formula'])) {
                        if ($item['salary_component'] === 'Indemnité') {
                            $values['IDM'] = $values['SB'] * 0.35;
                            return $values['IDM'];
                        } elseif ($item['salary_component'] === 'Taxe spéciale') {
                            $values['TSP'] = ($values['SB'] + $values['IDM']) * 0.21;
                            return $values['TSP'];
                        } elseif ($item['salary_component'] === 'Impôt') {
                            $values['IMP'] = ($values['SB'] + $values['IDM'] - $values['TSP']) * 0.1;
                            return $values['IMP'];
                        }
                    }
                }
            }

            return 0.0;
        } catch (\Exception $e) {
            Log::error("Erreur récupération composant {$componentName} pour {$assignment['salary_structure']} : " . $e->getMessage());
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

    /**
     * Calculer le nouveau salaire
     */
    private function calculateNewSalary(float $baseSalary, float $pourcentage, string $option): float
    {
        $multiplier = $option === 'augmentation' ? (1 + $pourcentage / 100) : (1 - $pourcentage / 100);
        return round($baseSalary * $multiplier, 2);
    }

    /**
     * Annuler l'assignment actuel, créer un nouveau, et mettre à jour les Salary Slips
     */
    private function updateSalaryAssignment(string $employeeName, array $currentAssignment, float $newBaseSalary): bool
    {
        try {
            $cancelResult = $this->erpApiService->executeMethod('frappe.client', 'cancel', [
                'doctype' => 'Salary Structure Assignment',
                'name' => $currentAssignment['name']
            ]);

            if (!$cancelResult || (isset($cancelResult['message']) && $cancelResult['message'] === false)) {
                Log::error("Échec annulation assignment {$currentAssignment['name']}", ['response' => $cancelResult]);
                return false;
            }

            $newAssignmentData = [
                'employee' => $employeeName,
                'salary_structure' => $currentAssignment['salary_structure'],
                'from_date' => date('Y-m-d'),
                'base' => $newBaseSalary,
                'company' => $currentAssignment['company'] ?? 'Orinasa SA',
                'currency' => $currentAssignment['currency'] ?? 'MGA', 
                'docstatus' => 1
            ];

            $newAssignment = $this->erpApiService->createResource('Salary Structure Assignment', $newAssignmentData);
            if (!$newAssignment || !isset($newAssignment['name'])) {
                Log::error("Échec création nouvel assignment pour {$employeeName}", ['response' => $newAssignment]);
                return false;
            }

            // Soumettre le nouvel assignment
            $submitResult = $this->erpApiService->executeMethod('frappe.client', 'submit', [
                'doctype' => 'Salary Structure Assignment',
                'name' => $newAssignment['name']
            ]);

            if (!$submitResult || (isset($submitResult['message']) && $submitResult['message'] === false)) {
                Log::error("Échec soumission nouvel assignment {$newAssignment['name']}", ['response' => $submitResult]);
                return false;
            }

            $this->updateSalarySlips($employeeName, $newAssignment);

            return true;
        } catch (\Exception $e) {
            Log::error("Erreur mise à jour assignment pour {$employeeName} : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mettre à jour ou créer des Salary Slips pour refléter le nouvel assignment
     */
    private function updateSalarySlips(string $employeeName, array $newAssignment): bool
    {
        try {
            $startDate = date('Y-m-01'); 
            $endDate = date('Y-m-t'); 

            // Vérifie les Salary Slips existants pour la période
            $filters = [
                ['employee', '=', $employeeName],
                ['start_date', '>=', $startDate],
                ['end_date', '<=', $endDate],
                ['docstatus', '<', 1]
            ];
            $existingSlips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => json_encode($filters),
                'fields' => json_encode(['name', 'docstatus'])
            ]);

            // Annule les Salary Slips existants non soumis
            foreach ($existingSlips as $slip) {
                if ($slip['docstatus'] == 0) { // Brouillon
                    $cancelResult = $this->erpApiService->executeMethod('frappe.client', 'cancel', [
                        'doctype' => 'Salary Slip',
                        'name' => $slip['name']
                    ]);
                    if (!$cancelResult) {
                        Log::error("Échec annulation Salary Slip {$slip['name']} pour {$employeeName}", ['response' => $cancelResult]);
                    }
                }
            }

            // Crée un nouveau Salary Slip
            $slipData = [
                'employee' => $employeeName,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'salary_structure' => $newAssignment['salary_structure'],
                'company' => $newAssignment['company'] ?? 'Orinasa SA',
                'currency' => $newAssignment['currency'] ?? 'MGA',
                'docstatus' => 1
            ];

            $newSlip = $this->erpApiService->createResource('Salary Slip', $slipData);
            if (!$newSlip || !isset($newSlip['name'])) {
                Log::error("Échec création nouveau Salary Slip pour {$employeeName}", ['response' => $newSlip]);
                return false;
            }

            // Soumettre le nouveau Salary Slip
            $submitResult = $this->erpApiService->executeMethod('frappe.client', 'submit', [
                'doctype' => 'Salary Slip',
                'name' => $newSlip['name']
            ]);

            if (!$submitResult || (isset($submitResult['message']) && $submitResult['message'] === false)) {
                Log::error("Échec soumission nouveau Salary Slip {$newSlip['name']} pour {$employeeName}", ['response' => $submitResult]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Erreur mise à jour Salary Slip pour {$employeeName} : " . $e->getMessage());
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