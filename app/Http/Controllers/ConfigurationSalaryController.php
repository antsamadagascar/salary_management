<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use App\Services\employee\EmployeeService;
use App\Services\config\ConfigSalaryService;
use App\Services\api\ErpApiService;

class ConfigurationSalaryController extends Controller
{
    private EmployeeService $employeeService;
    private ConfigSalaryService $configSalaryService;
    private ErpApiService $erpApiService;

    public function __construct(
        EmployeeService $employeeService,
        ErpApiService $erpApiService,
        ConfigSalaryService $configSalaryService
    ) {
        $this->employeeService = $employeeService;
        $this->erpApiService = $erpApiService;
        $this->configSalaryService = $configSalaryService;
    }

    public function index()
    {
        try {
            $employees = $this->employeeService->getEmployees(['status' => 'Active']);
            $salaryComponents = $this->configSalaryService->getSalaryComponents();
            $options = [
                ['name' => 'deductions'],
                ['name' => 'augmentation']
            ];
            $conditions = [
                ['name' => 'inferieur'],
                ['name' => 'superieur']
            ];

            return view('salaries.config.index', compact('employees', 'salaryComponents', 'options', 'conditions'));
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement des employés ou structures salariales: " . $e->getMessage());
            return redirect()->route('salaries.config.index')->with('error', 'Erreur lors du chargement des données: ' . $e->getMessage());
        }
    }

    /**
     * Traiter la configuration des salaires
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'salary_components' => 'required|string',
            'Montant' => 'required|numeric|min:0',
            'conditions' => 'required|in:inferieur,superieur',
            'options' => 'required|in:augmentation,deductions',
            'pourcentage' => 'required|numeric|min:0'
        ]);

        try {
            $employees = $this->employeeService->getEmployees(['status' => 'Active']);
            
            $result = $this->configSalaryService->modifyBaseSalary(
                $employees,
                $validated['salary_components'],
                $validated['Montant'],
                $validated['conditions'],
                $validated['options'],
                $validated['pourcentage']
            );

            if ($result['success']) {
                return redirect()->route('salaries.config.index')
                    ->with('success', $result['message'] . ' (' . count($result['modified_employees']) . ' employés modifiés)');
            } else {
                return redirect()->route('salaries.config.index')
                    ->with('error', $result['message']);
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors de la configuration des salaires : " . $e->getMessage());
            return redirect()->route('salaries.config.index')
                ->with('error', 'Erreur lors de la configuration : ' . $e->getMessage());
        }
    }

    /**
 * Prévisualiser les modifications avant application
 */
public function preview(Request $request)
{
    $validated = $request->validate([
        'salary_components' => 'required|string',
        'Montant' => 'required|numeric|min:0',
        'conditions' => 'required|in:inferieur,superieur',
        'options' => 'required|in:augmentation,deductions',
        'pourcentage' => 'required|numeric|min:0'
    ]);

    try {
        $employees = $this->employeeService->getEmployees(['status' => 'Active']);
        $preview = $this->configSalaryService->previewSalaryModification(
            $employees,
            $validated['salary_components'],
            $validated['Montant'],
            $validated['conditions'],
            $validated['options'],
            $validated['pourcentage']
        );

        return response()->json([
            'success' => true,
            'preview' => $preview,
            'count' => count($preview)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
}