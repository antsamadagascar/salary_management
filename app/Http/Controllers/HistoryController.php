<?php

namespace App\Http\Controllers;

use App\Services\employee\EmployeeService;
use App\Services\payroll\PayrollStatsService;
use App\Services\history\HistorySalaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HistoryController extends Controller
{
    protected $employeeService;
    protected $payrollStatsService;
    protected $historySalaryService;

    public function __construct(EmployeeService $employeeService, PayrollStatsService $payrollStatsService,HistorySalaryService $historySalaryService)
    {
        $this->employeeService = $employeeService;
        $this->payrollStatsService = $payrollStatsService;
        $this->historySalaryService = $historySalaryService;
    }

    public function index()
    {
        try {
            $employees = $this->employeeService->getEmployees(['status' => 'Active']);
            $availableYears = $this->payrollStatsService->getAvailableYears();
            
            return view('salaries.historiques.index', compact('employees', 'availableYears'));
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement des employés: " . $e->getMessage());
            return redirect()->route('salaries.generate.index')->with('error', 'Erreur lors du chargement des données: ' . $e->getMessage());
        }
    }

    public function show(Request $request)
    {
        $request->validate([
            'employe_id' => 'required|string',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'year' => 'nullable|integer|min:2020|max:2030'
        ]);

        try {
            // Récupére l'historique des salaires
            $salaryHistory = $this->historySalaryService->getSalaryHistory([
                'employe_id' => $request->employe_id,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'year' => $request->year
            ]);

            // Calcule les statistiques
            $statistics = $this->historySalaryService->calculateSalaryStatistics($salaryHistory);

            // Récupére les infos de l'employé
            $employee = $this->employeeService->getEmployeeById($request->employe_id);
            $employees = $this->employeeService->getEmployees(['status' => 'Active']);
            $availableYears = $this->payrollStatsService->getAvailableYears();

            return view('salaries.historiques.index', compact(
                'employees', 
                'availableYears', 
                'salaryHistory', 
                'statistics', 
                'employee',
                'request'
            ));

        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération de l'historique: " . $e->getMessage());
            return redirect()->back()->with('error', 'Erreur lors de la récupération des données: ' . $e->getMessage());
        }
    }
}