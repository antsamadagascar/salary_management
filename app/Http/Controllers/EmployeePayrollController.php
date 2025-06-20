<?php

namespace App\Http\Controllers;

use App\Services\payroll\PayrollDataService;
use App\Services\payroll\PayrollEmployeeService;
use App\Services\export\ExportService;
use App\Services\employee\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Carbon\Carbon;

class EmployeePayrollController extends Controller
{
    private PayrollDataService $payrollService;
    private ExportService $exportService;
    private EmployeeService $employeeService;
    private PayrollEmployeeService $payrollEmployeeService;

    public function __construct(PayrollDataService $payrollService, ExportService $exportService, EmployeeService $employeeService,PayrollEmployeeService $payrollEmployeeService)
    {
        $this->payrollService = $payrollService;
        $this->exportService = $exportService;
        $this->employeeService = $employeeService;
        $this->payrollEmployeeService = $payrollEmployeeService;
    }

    public function index(): View
    {
        try {
            $employees = $this->employeeService->getEmployees();
            return view('payroll.employee.employee-list', compact('employees'));
        } catch (\Exception $e) {
            return view('payroll.employee.employee-list', [
                'employees' => [],
                'error' => 'Erreur lors du chargement des employés: ' . $e->getMessage()
            ]);
        }
    }


    public function show(string $employeeId): View|RedirectResponse
    {
        try {
            $employee = $this->employeeService->getEmployeeByName($employeeId);
            
            if (!$employee) {
                return redirect()->route('payroll.index')->withError('Employé non trouvé');
            }

            $salariesByMonth = $this->payrollEmployeeService->getEmployeeSalariesByMonth($employeeId);
            $stats = $this->payrollEmployeeService->getPayrollStats($employeeId);
            
            return view('payroll.employee.employee-salary-sheet', compact('employee', 'salariesByMonth', 'stats'));
        } catch (\Exception $e) {
            return redirect()->route('payroll.index')->withError('Erreur lors du chargement de la fiche employé: ' . $e->getMessage());
        }
    }

    public function showSalarySlip(string $salarySlipId): View|RedirectResponse
    {
        try {
            $decodedId = urldecode($salarySlipId);
            \Log::info("ID brut: $salarySlipId");
            \Log::info("ID décodé: $decodedId");
    
            $salarySlip = $this->payrollService->getSalarySlip($decodedId);
            
            if (!$salarySlip) {
                \Log::warning("Fiche de paie non trouvée pour ID: $decodedId");
                return redirect()->route('payroll.index')->withError('Fiche de paie non trouvée ou accès non autorisé');
            }
    
            return view('payroll.employee.employee-salary-slip-details', compact('salarySlip'));
        } catch (\Exception $e) {
            \Log::error("Erreur lors du chargement de la fiche de paie $decodedId: " . $e->getMessage());
            return redirect()->route('payroll.index')->withError('Erreur lors du chargement de la fiche de paie: accès non autorisé ou erreur serveur');
        }
    }

    public function search(Request $request): View
    {
        $search = $request->get('search', '');
    
        try {
            $employees = $this->employeeService->searchEmployees($search);
    
            return view('payroll.employee.employee-list', compact('employees', 'search'));
        } catch (\Exception $e) {
            return view('payroll.employee.employee-list', [
                'employees' => [],
                'search' => $search,
                'error' => 'Erreur lors de la recherche: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Export pdf  salaires d'un employé pour un mois
     */
    public function exportMonthlyPdf(string $employeeId, string $month): Response|RedirectResponse
    {
        try {
            $employee = $this->employeeService->getEmployeeByName($employeeId);
            $salarySlips = $this->payrollEmployeeService->getSalarySlipsForMonth($employeeId, $month);
            

            if (!$employee) {
                return redirect()->route('payroll.index')->withError('Employé non trouvé');
            }

            $monthName = Carbon::createFromFormat('Y-m', $month)->locale('fr')->translatedFormat('F Y');
            $filename = 'salaires_' . str_replace([' ', '/'], '_', $employee['employee_name']) . '_' . $month . '.pdf';

            return $this->exportService->exportToPdf(
                'payroll.pdf.monthly-salary',
                [
                    'employee' => $employee,
                    'salarySlips' => $salarySlips,
                    'month' => $month,
                    'monthName' => $monthName
                ],
                $filename
            );
            
        } catch (\Exception $e) {
            \Log::error("Erreur lors de l'export PDF mensuel pour l'employé {$employeeId} mois {$month}: " . $e->getMessage());
            return redirect()->route('payroll.index')->withError('Erreur lors de l\'export PDF: ' . $e->getMessage());
        }
    }

    /**
     * Exporter la liste des employés avec leurs salaires en Excel
     */
    // public function exportEmployeesExcel(): Response|RedirectResponse
    // {
    //     try {
    //         $employees = $this->payrollEmployeeService->getEmployees();
    //         $data = [];
            
    //         foreach ($employees as $employee) {
    //             $stats = $this->payrollEmployeeService->getPayrollStats($employee['name']);
    //             $data[] = [
    //                 $employee['employee_number'] ?? '',
    //                 $employee['employee_name'] ?? '',
    //                 $employee['department'] ?? '',
    //                 $employee['designation'] ?? '',
    //                 number_format($stats['total_gross_pay'], 2),
    //                 number_format($stats['total_deductions'], 2),
    //                 number_format($stats['total_net_pay'], 2),
    //                 number_format($stats['average_net_pay'], 2),
    //                 $stats['months_count']
    //             ];
    //         }

    //         $headers = [
    //             'Matricule',
    //             'Nom',
    //             'Département',
    //             'Poste',
    //             'Total Brut',
    //             'Total Déductions',
    //             'Total Net',
    //             'Moyenne Net',
    //             'Nb Mois'
    //         ];

    //         return $this->exportService->exportToExcel($data, $headers, 'employes_salaires.xlsx');
    //     } catch (\Exception $e) {
    //         return redirect()->route('payroll.index')->withError('Erreur lors de l\'export Excel: ' . $e->getMessage());
    //     }
    // }

}