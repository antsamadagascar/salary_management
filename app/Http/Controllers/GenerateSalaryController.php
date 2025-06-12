<?php

namespace App\Http\Controllers;

use App\Services\employee\EmployeeService;
use App\Services\api\ErpApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateSalaryController extends Controller
{
    private EmployeeService $employeeService;
    private ErpApiService $erpApiService;

    public function __construct(EmployeeService $employeeService, ErpApiService $erpApiService)
    {
        $this->employeeService = $employeeService;
        $this->erpApiService = $erpApiService;
    }

    public function tableau(Request $request)
    {
        try {
            $employees = $this->employeeService->getEmployees(['filters' => ['status' => 'Active']]);
            return view('generate.index', compact('employees'));
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des employés: ' . $e->getMessage());
            return back()->with('error', 'Impossible de récupérer la liste des employés.');
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'salaire_base' => 'required|numeric|min:0',
            'date_debut' => 'required|date_format:Y-m',
            'date_fin' => 'required|date_format:Y-m|after_or_equal:date_debut'
        ]);

        try {
            $startDate = Carbon::createFromFormat('Y-m', $validated['date_debut'])->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $validated['date_fin'])->endOfMonth();
            $employeeId = $validated['employee_id'];
            $salaireBase = $validated['salaire_base'];

            // Récupérer les informations de l'employé
            $employee = $this->erpApiService->getResource('Employee', [
                'filters' => json_encode([['name', '=', $employeeId]]),
                'fields' => json_encode(['company', 'name', 'employee_name'])
            ]);

            if (empty($employee)) {
                Log::error("Employé $employeeId non trouvé.");
                return back()->with('error', "Employé $employeeId non trouvé.");
            }
            $company = $employee[0]['company'] ?? 'My Company'; // Valeur par défaut comme dans PayrollServiceImport
            $employeeName = $employee[0]['employee_name'] ?? $employeeId;

            // Récupérer une structure salariale par défaut
            $defaultSalaryStructure = $this->erpApiService->getResource('Salary Structure', [
                'filters' => json_encode([['is_active', '=', 1]]),
                'limit' => 1
            ]);

            if (empty($defaultSalaryStructure)) {
                Log::error('Aucune structure salariale active trouvée dans ERPNext.');
                return back()->with('error', 'Aucune structure salariale active trouvée dans ERPNext.');
            }
            $salaryStructureName = $defaultSalaryStructure[0]['name'];

            // Charger les fiches de paie existantes
            $existingPayrolls = $this->getExistingPayrolls();

            $results = [];

            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $monthStart = $currentDate->format('Y-m-d');
                $monthEnd = $currentDate->endOfMonth()->format('Y-m-d');
                $payrollPeriod = $currentDate->format('Y-m');
                $postingDate = $monthEnd; // Comme dans PayrollServiceImport

                Log::info("Traitement du mois $payrollPeriod pour l'employé $employeeId");

                // Vérifier les doublons
                $payrollKey = $this->generatePayrollKey($employeeId, $payrollPeriod);
                if (isset($existingPayrolls[$payrollKey])) {
                    Log::info("Fiche de paie existante pour $employeeId à $payrollPeriod. Ignoré.");
                    $results[] = false;
                    $currentDate->addMonth();
                    continue;
                }

                // Vérifier si une Salary Structure Assignment existe
                $assignmentFromDate = $monthStart;
                $salaryStructureAssignment = $this->erpApiService->getResource('Salary Structure Assignment', [
                    'filters' => json_encode([
                        ['employee', '=', $employeeId],
                        ['from_date', '<=', $monthStart],
                        ['docstatus', '=', 1],
                    ]),
                    'order_by' => 'from_date desc',
                    'limit' => 1
                ]);

                if (empty($salaryStructureAssignment)) {
                    Log::info("Aucune Salary Structure Assignment trouvée pour $employeeId à $monthStart. Tentative de création.");

                    // Créer une Salary Structure Assignment
                    $assignmentData = [
                        'employee' => $employeeId,
                        'employee_name' => $employeeName,
                        'salary_structure' => $salaryStructureName,
                        'from_date' => $assignmentFromDate,
                        'company' => $company,
                        'base' => $salaireBase,
                        'docstatus' => 1
                    ];

                    try {
                        $result = $this->erpApiService->createResource('Salary Structure Assignment', $assignmentData);
                        if (!$result) {
                            Log::error("Échec de la création de Salary Structure Assignment pour $employeeId à $monthStart.");
                            return back()->with('error', "Échec de la création de la structure salariale pour $employeeName pour le mois de " . $currentDate->format('Y-m') . ".");
                        }
                        Log::info("Salary Structure Assignment créée avec succès pour $employeeId à $monthStart.");
                    } catch (\Exception $e) {
                        Log::error("Erreur lors de la création de Salary Structure Assignment pour $employeeId à $monthStart: " . $e->getMessage());
                        return back()->with('error', "Erreur lors de la création de la structure salariale pour $employeeName: " . $e->getMessage());
                    }
                }

                // Vérifier si un salaire existe pour ce mois
                $existingSalary = $this->erpApiService->getResource('Salary Slip', [
                    'filters' => json_encode([
                        ['employee', '=', $employeeId],
                        ['start_date', '>=', $monthStart],
                        ['start_date', '<=', $monthEnd]
                    ])
                ]);

                if (empty($existingSalary)) {
                    // Chercher le dernier salaire avant la date de début
                    $previousSalary = $this->erpApiService->getResource('Salary Slip', [
                        'filters' => json_encode([
                            ['employee', '=', $employeeId],
                            ['start_date', '<', $monthStart]
                        ]),
                        'order_by' => 'start_date desc',
                        'limit' => 1
                    ]);

                    $salaryAmount = !empty($previousSalary) ? ($previousSalary[0]['gross_pay'] ?? $salaireBase) : $salaireBase;

                    $salaryData = [
                        'employee' => $employeeId,
                        'employee_name' => $employeeName,
                        'posting_date' => $postingDate,
                        'payroll_period' => $payrollPeriod,
                        'start_date' => $monthStart,
                        'end_date' => $monthEnd,
                        'gross_pay' => $salaryAmount,
                        'salary_structure' => $salaryStructureName,
                        'company' => $company,
                        'payroll_frequency' => 'Monthly',
                        'status' => 'Draft',
                        'docstatus' => 1
                    ];

                    try {
                        $result = $this->erpApiService->createResource('Salary Slip', $salaryData);
                        if ($result) {
                            $results[] = $salaryData;
                            $existingPayrolls[$payrollKey] = true; // Ajouter au cache
                            Log::info("Salary Slip créé avec succès pour $employeeId à $payrollPeriod.");
                        } else {
                            $results[] = false;
                            Log::error("Échec de la création de Salary Slip pour $employeeId à $payrollPeriod.");
                        }
                    } catch (\Exception $e) {
                        $results[] = false;
                        Log::error("Erreur lors de la création de Salary Slip pour $employeeId à $payrollPeriod: " . $e->getMessage());
                    }
                } else {
                    Log::info("Salary Slip existant trouvé pour $employeeId à $payrollPeriod. Ignoré.");
                    $results[] = false;
                }

                $currentDate->addMonth();
            }

            if (in_array(false, $results, true)) {
                Log::warning('Certains salaires n\'ont pas pu être générés pour ' . $employeeId);
                return back()->with('error', 'Certains salaires n\'ont pas pu être générés.');
            }

            Log::info(count(array_filter($results)) . ' salaires générés avec succès pour ' . $employeeId);
            return redirect()->route('generate.tableau')->with('success', count(array_filter($results)) . ' salaires générés avec succès.');
        } catch (\Exception $e) {
            Log::error('Erreur générale lors de la génération des salaires pour ' . $employeeId . ': ' . $e->getMessage());
            return back()->with('error', 'Erreur lors de la génération des salaires: ' . $e->getMessage());
        }
    }

    private function getExistingPayrolls(): array
    {
        try {
            $payrolls = $this->erpApiService->getResource('Salary Slip', ['limit_page_length' => 2000]);
            $existing = [];
            foreach ($payrolls as $payroll) {
                if (!empty($payroll['start_date'])) {
                    $period = Carbon::parse($payroll['start_date'])->format('Y-m');
                    $key = $this->generatePayrollKey($payroll['employee'], $period);
                    $existing[$key] = true;
                }
            }
            Log::info("Fiches de paie existantes chargées: " . count($existing));
            return $existing;
        } catch (\Exception $e) {
            Log::warning('Impossible de récupérer la liste des fiches de paie existantes: ' . $e->getMessage());
            return [];
        }
    }

    private function generatePayrollKey(string $employeeRef, string $period): string
    {
        return $employeeRef . '_' . $period;
    }
}