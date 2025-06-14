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
                        
                        // FIXED: Get all available salary components for formula variables
                        $variables = $this->buildFormulaVariables($assignment, $structure);
                        
                        return $this->safeEvaluateFormula($formula, $variables);
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
     * FIXED: Build comprehensive variables for formula evaluation
     */
    private function buildFormulaVariables(array $assignment, array $structure): array
    {
        $variables = [
            'base' => (float) ($assignment['base'] ?? 0),
            'SB' => (float) ($assignment['base'] ?? 0), // Salary Base
            'BS' => (float) ($assignment['base'] ?? 0), // Base Salary
        ];

        try {
            // Add all earnings and deductions as variables
            $allComponents = array_merge($structure['earnings'] ?? [], $structure['deductions'] ?? []);
            
            foreach ($allComponents as $component) {
                $componentName = $component['salary_component'] ?? '';
                if (empty($componentName)) continue;

                // Calculate component value
                $value = 0.0;
                if (isset($component['amount']) && !empty($component['amount']) && empty($component['amount_based_on_formula'])) {
                    $value = (float) $component['amount'];
                } elseif (!empty($component['formula'])) {
                    // For nested formulas, try to evaluate with current variables
                    $value = $this->safeEvaluateFormula($component['formula'], $variables);
                }

                // Add common abbreviations
                $variables[$componentName] = $value;
                
                // Common abbreviations for salary components
                $abbreviations = $this->getComponentAbbreviations($componentName);
                foreach ($abbreviations as $abbr) {
                    $variables[$abbr] = $value;
                }
            }

            Log::debug("Variables construites pour formule", ['variables' => $variables]);
            
        } catch (\Exception $e) {
            Log::error("Erreur construction variables formule : " . $e->getMessage());
        }

        return $variables;
    }

    /**
     * Get common abbreviations for salary components
     */
    private function getComponentAbbreviations(string $componentName): array
    {
        $abbreviations = [];
        
        // Common patterns
        $patterns = [
            'Basic Salary' => ['BS', 'SB', 'BASE'],
            'House Rent Allowance' => ['HRA'],
            'Dearness Allowance' => ['DA'],
            'Medical Allowance' => ['MA'],
            'Transport Allowance' => ['TA'],
            'Provident Fund' => ['PF'],
            'Professional Tax' => ['PT'],
            'Income Tax' => ['IT', 'TDS'],
            'Indemnité' => ['IND'],
            'Prime' => ['PRIME'],
            'Allocation' => ['ALL'],
        ];

        foreach ($patterns as $pattern => $abbrs) {
            if (stripos($componentName, $pattern) !== false) {
                $abbreviations = array_merge($abbreviations, $abbrs);
            }
        }

        // Generate abbreviation from component name
        $words = preg_split('/[\s\-_]+/', $componentName);
        if (count($words) > 1) {
            $abbr = strtoupper(implode('', array_map(fn($w) => substr($w, 0, 1), $words)));
            $abbreviations[] = $abbr;
        }

        return array_unique($abbreviations);
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

    private function safeEvaluateFormula(string $formula, array $vars): float
    {
        try {
            $originalFormula = $formula;
            
            // Replace variables in formula
            foreach ($vars as $key => $value) {
                $formula = str_replace($key, (string)(float) $value, $formula);
            }

            // FIXED: Check if all variables were replaced
            if (preg_match('/[A-Z_]+/', $formula)) {
                $unreplacedVars = [];
                preg_match_all('/[A-Z_]+/', $formula, $matches);
                $unreplacedVars = array_unique($matches[0]);
                
                Log::warning("Variables non remplacées dans formule", [
                    'formula' => $originalFormula,
                    'unreplaced' => $unreplacedVars,
                    'available_vars' => array_keys($vars)
                ]);
                
                // Try to replace with 0 for missing variables
                foreach ($unreplacedVars as $var) {
                    if (!isset($vars[$var])) {
                        $formula = str_replace($var, '0', $formula);
                        Log::info("Variable {$var} remplacée par 0");
                    }
                }
            }

            // Validate formula contains only numbers and operators
            if (!preg_match('/^[\d\.\+\-\*\/\(\)\s]+$/', $formula)) {
                Log::error("Formule contient des caractères invalides après remplacement", [
                    'original' => $originalFormula,
                    'processed' => $formula
                ]);
                return 0.0;
            }

            $result = eval("return $formula;");
            return is_numeric($result) ? (float)$result : 0.0;
            
        } catch (\Throwable $e) {
            Log::error("Erreur d'évaluation formule [$originalFormula] -> [$formula]: " . $e->getMessage());
            return 0.0;
        }
    }

    private function calculateNewSalary(float $baseSalary, float $pourcentage, string $option): float
    {
        $multiplier = $option === 'augmentation' ? (1 + $pourcentage / 100) : (1 - $pourcentage / 100);
        return round($baseSalary * $multiplier, 2);
    }

    private function manageSalarySlips(string $employeeName, string $salaryStructure, float $newBaseSalary, string $fromDate, string $company): bool
    {
        try {
            Log::info("Début gestion fiches de paie pour employé {$employeeName}, période {$fromDate}");

            $payrollDate = Carbon::createFromFormat('Y-m-d', $fromDate);
            $startDate = $payrollDate->copy()->startOfMonth()->format('Y-m-d');
            $endDate = $payrollDate->copy()->endOfMonth()->format('Y-m-d');
            
            Log::debug("Recherche fiches de paie existantes", [
                'employee' => $employeeName,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            $existingSlips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => json_encode([
                    ['employee', '=', $employeeName],
                    ['start_date', '>=', $startDate],
                    ['end_date', '<=', $endDate],
                    ['docstatus', '!=', 2] // Not cancelled
                ]),
                'fields' => json_encode(['name', 'docstatus', 'start_date', 'end_date'])
            ]);

            Log::info("Fiches de paie existantes trouvées : " . count($existingSlips));

            // Cancel existing slips that are submitted
            foreach ($existingSlips as $slip) {
                if ($slip['docstatus'] == 1) { // Submitted
                    Log::debug("Annulation fiche de paie {$slip['name']} pour {$employeeName}");
                    $cancelResult = $this->erpApiService->executeMethod('frappe.client', 'cancel', [
                        'doctype' => 'Salary Slip',
                        'name' => $slip['name']
                    ]);

                    if (!$cancelResult || (isset($cancelResult['message']) && $cancelResult['message'] === false)) {
                        Log::error("Échec annulation fiche de paie {$slip['name']}", [
                            'response' => $cancelResult
                        ]);
                        return false;
                    }
                    Log::info("Fiche de paie {$slip['name']} annulée avec succès");
                } elseif ($slip['docstatus'] == 0) { // Draft - delete it
                    Log::debug("Suppression fiche de paie draft {$slip['name']} pour {$employeeName}");
                    $deleteResult = $this->erpApiService->deleteResource('Salary Slip', $slip['name']);
                    if ($deleteResult) {
                        Log::info("Fiche de paie draft {$slip['name']} supprimée avec succès");
                    }
                }
            }

            // FIXED: Create new salary slip with correct data structure
            $payrollData = [
                'employee' => $employeeName,
                'salary_structure' => $salaryStructure,
                'company' => $company,
                'posting_date' => $endDate, // End of month
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payroll_frequency' => 'Monthly',
                'docstatus' => 0 // Create as draft first
            ];

            Log::debug("Création nouvelle fiche de paie", ['data' => $payrollData]);

            $newSlip = $this->erpApiService->createResource('Salary Slip', $payrollData);

            if (!$newSlip) {
                Log::error("Échec création nouvelle fiche de paie pour {$employeeName}");
                return false;
            }

            // Get the created slip name
            $slipName = null;
            if (is_array($newSlip) && isset($newSlip['name'])) {
                $slipName = $newSlip['name'];
            } elseif (is_string($newSlip)) {
                $slipName = $newSlip;
            }

            if ($slipName) {
                Log::info("Fiche de paie {$slipName} créée pour {$employeeName}");
                
                // Submit the salary slip
                try {
                    $submitResult = $this->erpApiService->executeMethod('frappe.client', 'submit', [
                        'doctype' => 'Salary Slip',
                        'name' => $slipName
                    ]);
                    
                    if ($submitResult) {
                        Log::info("Fiche de paie {$slipName} soumise avec succès");
                    } else {
                        Log::warning("Fiche de paie {$slipName} créée mais non soumise");
                    }
                } catch (\Exception $e) {
                    Log::warning("Erreur soumission fiche de paie {$slipName}: " . $e->getMessage());
                    // Continue anyway as the slip was created
                }
            } else {
                Log::info("Fiche de paie créée avec succès pour {$employeeName} (nom non disponible)");
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Erreur gestion fiches de paie pour {$employeeName}: " . $e->getMessage());
            return false;
        }
    }

    private function updateSalaryAssignment(string $employeeName, array $currentAssignment, float $newBaseSalary): bool
    {
        try {
            Log::info("Début mise à jour assignment pour {$employeeName}");

            $fromDate = date('Y-m-d');
            $payrollDate = Carbon::createFromFormat('Y-m-d', $fromDate);
            $assignmentFromDate = $payrollDate->copy()->startOfMonth()->format('Y-m-d');
            $nextMonth = $payrollDate->copy()->addMonth()->startOfMonth()->format('Y-m-d');

            // Get existing assignments
            $existingAssignments = $this->erpApiService->getResource('Salary Structure Assignment', [
                'filters' => json_encode([
                    ['employee', '=', $employeeName],
                    ['salary_structure', '=', $currentAssignment['salary_structure']],
                    ['from_date', '>=', $assignmentFromDate],
                    ['from_date', '<', $nextMonth],
                    ['docstatus', '!=', 2]
                ]),
                'fields' => json_encode(['name', 'base', 'docstatus'])
            ]);

            // Cancel existing assignments
            foreach ($existingAssignments as $assignment) {
                if ($assignment['docstatus'] == 1) {
                    $cancelResult = $this->erpApiService->executeMethod('frappe.client', 'cancel', [
                        'doctype' => 'Salary Structure Assignment',
                        'name' => $assignment['name']
                    ]);

                    if (!$cancelResult) {
                        Log::error("Échec annulation assignment {$assignment['name']}");
                        return false;
                    }
                    Log::info("Assignment {$assignment['name']} annulé avec succès");
                }
            }

            // Create new assignment
            $newAssignmentData = [
                'employee' => $employeeName,
                'salary_structure' => $currentAssignment['salary_structure'],
                'from_date' => $assignmentFromDate,
                'base' => $newBaseSalary,
                'company' => $currentAssignment['company'] ?? 'Orinasa SA',
                'currency' => $currentAssignment['currency'] ?? 'MGA',
                'docstatus' => 1
            ];

            Log::debug("Données nouvelle assignation", ['data' => $newAssignmentData]);
            
            $newAssignment = $this->erpApiService->createResource('Salary Structure Assignment', $newAssignmentData);

            if ($newAssignment === false || $newAssignment === null) {
                Log::error("Échec création nouvel assignment pour {$employeeName} - API returned false/null");
                return false;
            }
            
            $assignmentName = null;
            if (is_array($newAssignment) && isset($newAssignment['name'])) {
                $assignmentName = $newAssignment['name'];
            } elseif (is_string($newAssignment)) {
                $assignmentName = $newAssignment;
            } elseif ($newAssignment === true) {
                Log::info("Assignment créé avec succès (API returned true) pour {$employeeName}");

                $createdAssignments = $this->erpApiService->getResource('Salary Structure Assignment', [
                    'filters' => json_encode([
                        ['employee', '=', $employeeName],
                        ['salary_structure', '=', $currentAssignment['salary_structure']],
                        ['from_date', '=', $assignmentFromDate],
                        ['base', '=', $newBaseSalary]
                    ]),
                    'fields' => json_encode(['name']),
                    'limit_page_length' => 1
                ]);
                
                if (!empty($createdAssignments)) {
                    $assignmentName = $createdAssignments[0]['name'];
                    Log::info("Assignment trouvé: {$assignmentName}");
                } else {
                    Log::warning("Assignment créé mais nom non trouvé pour {$employeeName}");
                }
            }

            if ($assignmentName) {
                Log::info("Nouvel assignment créé : {$assignmentName}");
            } else {
                Log::info("Assignment créé pour {$employeeName} (nom non disponible)");
            }

            // Manage salary slips
            $manageSlipsResult = $this->manageSalarySlips(
                $employeeName,
                $currentAssignment['salary_structure'],
                $newBaseSalary,
                $assignmentFromDate,
                $currentAssignment['company'] ?? 'Orinasa SA'
            );

            if (!$manageSlipsResult) {
                Log::error("Échec gestion fiches de paie pour {$employeeName}");
                return false;
            }

            Log::info("Mise à jour assignment et fiches de paie terminée pour {$employeeName}");
            return true;

        } catch (\Exception $e) {
            Log::error("Erreur mise à jour assignment pour {$employeeName}: " . $e->getMessage());
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