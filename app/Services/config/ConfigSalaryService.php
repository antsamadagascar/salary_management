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

                $componentValue = $this->getComponentValue($assignment, $salaryComponent);
                if ($this->checkCondition($componentValue, $montant, $condition)) {
                    $currentBaseSalary = $assignment['base'] ?? 0;
                    $newBaseSalary = $this->calculateNewSalary($currentBaseSalary, $pourcentage, $option);

                    // Utilise le from_date de l'assignment existant ou le mois spécifié
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

    private function buildFormulaVariables(array $assignment, array $structure): array
    {
        $variables = [
            'base' => (float) ($assignment['base'] ?? 0),
            'SB' => (float) ($assignment['base'] ?? 0),
            'BS' => (float) ($assignment['base'] ?? 0),
        ];

        try {
            $allComponents = array_merge($structure['earnings'] ?? [], $structure['deductions'] ?? []);
            
            foreach ($allComponents as $component) {
                $componentName = $component['salary_component'] ?? '';
                if (empty($componentName)) continue;

                $value = 0.0;
                if (isset($component['amount']) && !empty($component['amount']) && empty($component['amount_based_on_formula'])) {
                    $value = (float) $component['amount'];
                } elseif (!empty($component['formula'])) {
                    $value = $this->safeEvaluateFormula($component['formula'], $variables);
                }

                $variables[$componentName] = $value;
                
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

    private function getComponentAbbreviations(string $componentName): array
    {
        $abbreviations = [];
        
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
            
            foreach ($vars as $key => $value) {
                $formula = str_replace($key, (string)(float) $value, $formula);
            }

            if (preg_match('/[A-Z_]+/', $formula)) {
                $unreplacedVars = [];
                preg_match_all('/[A-Z_]+/', $formula, $matches);
                $unreplacedVars = array_unique($matches[0]);
                
                Log::warning("Variables non remplacées dans formule", [
                    'formula' => $originalFormula,
                    'unreplaced' => $unreplacedVars,
                    'available_vars' => array_keys($vars)
                ]);
                
                foreach ($unreplacedVars as $var) {
                    if (!isset($vars[$var])) {
                        $formula = str_replace($var, '0', $formula);
                        Log::info("Variable {$var} remplacée par 0");
                    }
                }
            }

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

    /**
     *  Mise à jour en gardant la date originale de l'assignment
     */
    private function updateSalaryForExistingMonth(
        string $employeeName, 
        array $currentAssignment, 
        float $newBaseSalary, 
        string $targetMonth
    ): bool {
        try {
            // Utilise la from_date EXISTANTE de l'assignment, pas la date actuelle
            $originalFromDate = $currentAssignment['from_date'];
            
            Log::info("Début mise à jour salary pour {$employeeName} - from_date ORIGINAL: {$originalFromDate}");

            // Calcul les dates pour les salary slips basées sur le mois cible
            $targetDate = Carbon::createFromFormat('Y-m', $targetMonth);
            $slipStartDate = $targetDate->copy()->startOfMonth()->format('Y-m-d');
            $slipEndDate = $targetDate->copy()->endOfMonth()->format('Y-m-d');
            
            Log::debug("Dates pour salary slips", [
                'assignment_from_date' => $originalFromDate,
                'slip_start_date' => $slipStartDate,
                'slip_end_date' => $slipEndDate
            ]);

            // 1. SALARY STRUCTURE ASSIGNMENT - on Garde la MÊME from_date
            $assignmentSuccess = $this->updateSalaryStructureAssignmentKeepDate(
                $employeeName, 
                $currentAssignment, 
                $newBaseSalary, 
                $originalFromDate // ON GARDE LA DATE ORIGINALE
            );

            if (!$assignmentSuccess) {
                Log::error("Échec mise à jour Salary Structure Assignment pour {$employeeName}");
                return false;
            }

            // 2. SALARY SLIP - Mettre à jour pour le mois spécifié
            $slipSuccess = $this->updateSalarySlipForMonth(
                $employeeName,
                $currentAssignment['salary_structure'],
                $newBaseSalary,
                $slipStartDate,
                $slipEndDate,
                $currentAssignment['company'] ?? 'Orinasa SA'
            );

            if (!$slipSuccess) {
                Log::error("Échec mise à jour Salary Slip pour {$employeeName}");
                return false;
            }

            Log::info("Mise à jour complète réussie pour {$employeeName} - from_date conservée: {$originalFromDate}");
            return true;

        } catch (\Exception $e) {
            Log::error("Erreur mise à jour salary pour {$employeeName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NOUVEAU: Mise à jour Salary Structure Assignment en gardant la date originale
     */
    private function updateSalaryStructureAssignmentKeepDate(
        string $employeeName, 
        array $currentAssignment, 
        float $newBaseSalary, 
        string $originalFromDate
    ): bool {
        try {
            Log::info("Mise à jour Salary Structure Assignment pour {$employeeName} - GARDER date: {$originalFromDate}");

            // Recherche l'assignment EXACT avec la même from_date
            $existingAssignments = $this->erpApiService->getResource('Salary Structure Assignment', [
                'filters' => json_encode([
                    ['employee', '=', $employeeName],
                    ['salary_structure', '=', $currentAssignment['salary_structure']],
                    ['from_date', '=', $originalFromDate],
                    ['docstatus', '!=', 2]  //SALARY STRUCTURE ASSIGNEMENT Pas annulé
                ]),
                'fields' => json_encode(['name', 'base', 'docstatus', 'from_date'])
            ]);

            Log::debug("Assignments existants avec from_date {$originalFromDate}: " . count($existingAssignments));

            // ANNULATION de l'assignment existant avec cette date
            foreach ($existingAssignments as $assignment) {
                if ($assignment['docstatus'] == 1) { // Soumis
                    Log::debug("Annulation assignment {$assignment['name']} avec from_date {$originalFromDate}");
                    $cancelResult = $this->erpApiService->executeMethod('frappe.client', 'cancel', [
                        'doctype' => 'Salary Structure Assignment',
                        'name' => $assignment['name']
                    ]);

                    if (!$cancelResult) {
                        Log::error("Échec annulation assignment {$assignment['name']}");
                        return false;
                    }
                    Log::info("Assignment {$assignment['name']} annulé avec succès");
                } elseif ($assignment['docstatus'] == 0) { // Brouillon
                    Log::debug("Suppression assignment draft {$assignment['name']}");
                    $this->erpApiService->deleteResource('Salary Structure Assignment', $assignment['name']);
                }
            }

            // Création du nouveau assignment avec la MÊME from_date originale
            $newAssignmentData = [
                'employee' => $employeeName,
                'salary_structure' => $currentAssignment['salary_structure'],
                'from_date' => $originalFromDate, // ON GARDE LA DATE ORIGINALE
                'base' => $newBaseSalary,
                'company' => $currentAssignment['company'] ?? 'Orinasa SA',
                'currency' => $currentAssignment['currency'] ?? 'MGA',
                'docstatus' => 1
            ];

            Log::debug("Création nouvel assignment avec from_date ORIGINALE", [
                'data' => $newAssignmentData,
                'old_base' => $currentAssignment['base'],
                'new_base' => $newBaseSalary
            ]);
            
            $newAssignment = $this->erpApiService->createResource('Salary Structure Assignment', $newAssignmentData);

            if (!$newAssignment) {
                Log::error("Échec création nouvel assignment pour {$employeeName}");
                return false;
            }

            Log::info("Nouvel assignment créé avec succès pour {$employeeName} avec from_date {$originalFromDate}");
            return true;

        } catch (\Exception $e) {
            Log::error("Erreur mise à jour Salary Structure Assignment: " . $e->getMessage());
            return false;
        }
    }

    /**
     *  Mise à jour Salary Slip pour un mois spécifique
     */
    private function updateSalarySlipForMonth(
        string $employeeName,
        string $salaryStructure,
        float $newBaseSalary,
        string $startDate,
        string $endDate,
        string $company
    ): bool {
        try {
            Log::info("Mise à jour Salary Slip pour {$employeeName} - période: {$startDate} à {$endDate}");

            // Recherche les salary slips existants pour cette période
            $existingSlips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => json_encode([
                    ['employee', '=', $employeeName],
                    ['start_date', '=', $startDate],
                    ['end_date', '=', $endDate],
                    ['docstatus', '!=', 2] // Pas annulé
                ]),
                'fields' => json_encode(['name', 'docstatus', 'start_date', 'end_date'])
            ]);

            Log::debug("Salary slips existants trouvés: " . count($existingSlips));

            // Annuler/supprimer les slips existants
            foreach ($existingSlips as $slip) {
                if ($slip['docstatus'] == 1) { // Soumis
                    Log::debug("Annulation salary slip {$slip['name']}");
                    $cancelResult = $this->erpApiService->executeMethod('frappe.client', 'cancel', [
                        'doctype' => 'Salary Slip',
                        'name' => $slip['name']
                    ]);

                    if (!$cancelResult) {
                        Log::error("Échec annulation salary slip {$slip['name']}");
                        return false;
                    }
                    Log::info("Salary slip {$slip['name']} annulé avec succès");
                } elseif ($slip['docstatus'] == 0) { // Brouillon
                    Log::debug("Suppression salary slip draft {$slip['name']}");
                    $this->erpApiService->deleteResource('Salary Slip', $slip['name']);
                }
            }

            // Création  nouveau salary slip pour la même période
            $payrollData = [
                'employee' => $employeeName,
                'salary_structure' => $salaryStructure,
                'company' => $company,
                'posting_date' => $endDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payroll_frequency' => 'Monthly',
                'docstatus' => 1 
            ];

            Log::debug("Création nouveau salary slip", ['data' => $payrollData]);

            $newSlip = $this->erpApiService->createResource('Salary Slip', $payrollData);

            if (!$newSlip) {
                Log::error("Échec création nouveau salary slip pour {$employeeName}");
                return false;
            }

            // Obtient le nom du slip créé
            $slipName = null;
            if (is_array($newSlip) && isset($newSlip['name'])) {
                $slipName = $newSlip['name'];
            } elseif (is_string($newSlip)) {
                $slipName = $newSlip;
            }

            if ($slipName) {
                Log::info("Salary slip {$slipName} créé pour {$employeeName}");
                
                // Soumettre le salary slip
                try {
                    $submitResult = $this->erpApiService->executeMethod('frappe.client', 'submit', [
                        'doctype' => 'Salary Slip',
                        'name' => $slipName
                    ]);
                    
                    if ($submitResult) {
                        Log::info("Salary slip {$slipName} soumis avec succès");
                    }
                } catch (\Exception $e) {
                    Log::warning("Erreur soumission salary slip {$slipName}: " . $e->getMessage());
                }
            }

            Log::info("Salary slip mis à jour avec succès pour {$employeeName}");
            return true;

        } catch (\Exception $e) {
            Log::error("Erreur mise à jour Salary Slip: " . $e->getMessage());
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