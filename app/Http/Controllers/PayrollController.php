<?php

namespace App\Http\Controllers;

use App\Services\PayrollService;
use App\Services\ExportService;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Carbon\Carbon;

class PayrollController extends Controller
{
    private PayrollService $payrollService;
    private ExportService $exportService;
    private EmployeeService $employeeService;

    public function __construct(PayrollService $payrollService, ExportService $exportService,EmployeeService $employeeService)
    {
        $this->payrollService = $payrollService;
        $this->exportService = $exportService;
        $this->employeeService =$employeeService;
    }

    /**
     * Afficher la liste des employés
     */
    public function index(): View
    {
        try {
            $employees = $this->employeeService->getEmployees();
            return view('payroll.index', compact('employees'));
        } catch (\Exception $e) {
            return view('payroll.index', [
                'employees' => [],
                'error' => 'Erreur lors du chargement des employés: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Afficher la fiche employé avec ses salaires par mois
     */
    public function show(string $employeeId): View
    {
        try {
            $employee = $this->employeeService->getEmployeeByName($employeeId);
            
            if (!$employee) {
                abort(404, 'Employé non trouvé');
            }

            $salariesByMonth = $this->payrollService->getEmployeeSalariesByMonth($employeeId);
            $stats = $this->payrollService->getPayrollStats($employeeId);

            return view('payroll.show', compact('employee', 'salariesByMonth', 'stats'));
        } catch (\Exception $e) {
            return back()->withError('Erreur lors du chargement de la fiche employé: ' . $e->getMessage());
        }
    }

    /**
     * Afficher une fiche de paie spécifique
     */
    public function showSalarySlip(string $salarySlipId): View
    {
        try {
            $salarySlip = $this->payrollService->getSalarySlip($salarySlipId);
            
            if (!$salarySlip) {
                abort(404, 'Fiche de paie non trouvée');
            }

            return view('payroll.salary-slip', compact('salarySlip'));
        } catch (\Exception $e) {
            return back()->withError('Erreur lors du chargement de la fiche de paie: ' . $e->getMessage());
        }
    }

    /**
     * Exporter une fiche de paie en PDF
     */
    public function exportSalarySlipPdf(string $salarySlipId): Response
    {
        try {
            $salarySlip = $this->payrollService->getSalarySlip($salarySlipId);
            
            if (!$salarySlip) {
                abort(404, 'Fiche de paie non trouvée');
            }

            $filename = 'fiche_paie_' . $salarySlip['employee_name'] . '_' . 
                       Carbon::parse($salarySlip['posting_date'])->format('Y_m') . '.pdf';

            return $this->exportService->exportToPdf(
                'payroll.pdf.salary-slip',
                ['salarySlip' => $salarySlip],
                $filename
            );
        } catch (\Exception $e) {
            return back()->withError('Erreur lors de l\'export PDF: ' . $e->getMessage());
        }
    }

    /**
     * Exporter les salaires d'un employé pour un mois en PDF
     */
    public function exportMonthlyPdf(string $employeeId, string $month): Response
    {
        try {
            $employee = $this->payrollService->getEmployee($employeeId);
            $salarySlips = $this->payrollService->getSalarySlipsForMonth($employeeId, $month);
            
            if (!$employee) {
                abort(404, 'Employé non trouvé');
            }

            $monthName = Carbon::createFromFormat('Y-m', $month)->locale('fr')->translatedFormat('F Y');
            $filename = 'salaires_' . $employee['employee_name'] . '_' . $month . '.pdf';

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
            return back()->withError('Erreur lors de l\'export PDF: ' . $e->getMessage());
        }
    }

    /**
     * Exporter la liste des employés avec leurs salaires en Excel
     */
    public function exportEmployeesExcel(): Response
    {
        try {
            $employees = $this->payrollService->getEmployees();
            $data = [];
            
            foreach ($employees as $employee) {
                $stats = $this->payrollService->getPayrollStats($employee['name']);
                $data[] = [
                    $employee['employee_number'] ?? '',
                    $employee['employee_name'] ?? '',
                    $employee['department'] ?? '',
                    $employee['designation'] ?? '',
                    number_format($stats['total_gross_pay'], 2),
                    number_format($stats['total_deductions'], 2),
                    number_format($stats['total_net_pay'], 2),
                    number_format($stats['average_net_pay'], 2),
                    $stats['months_count']
                ];
            }

            $headers = [
                'Matricule',
                'Nom',
                'Département',
                'Poste',
                'Total Brut',
                'Total Déductions',
                'Total Net',
                'Moyenne Net',
                'Nb Mois'
            ];

            return $this->exportService->exportToExcel($data, $headers, 'employes_salaires.xlsx');
        } catch (\Exception $e) {
            return back()->withError('Erreur lors de l\'export Excel: ' . $e->getMessage());
        }
    }

    /**
     * Rechercher des employés
     */
    public function search(Request $request): View
    {
        $search = $request->get('search', '');
        
        try {
            $employees = $this->payrollService->getEmployees();
            
            if ($search) {
                $employees = array_filter($employees, function ($employee) use ($search) {
                    return stripos($employee['employee_name'], $search) !== false ||
                           stripos($employee['employee_number'] ?? '', $search) !== false ||
                           stripos($employee['department'] ?? '', $search) !== false;
                });
            }

            return view('payroll.index', compact('employees', 'search'));
        } catch (\Exception $e) {
            return view('payroll.index', [
                'employees' => [],
                'search' => $search,
                'error' => 'Erreur lors de la recherche: ' . $e->getMessage()
            ]);
        }
    }
}