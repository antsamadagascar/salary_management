<?php

namespace App\Http\Controllers;

use App\Services\employee\EmployeeService;
use App\Services\generate\SalaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GenerateSalaryController extends Controller
{
    protected $employeeService;
    protected $salaryService;

    public function __construct(EmployeeService $employeeService, SalaryService $salaryService)
    {
        $this->employeeService = $employeeService;
        $this->salaryService = $salaryService;
    }

    public function index()
    {
        try {
            $employees = $this->employeeService->getEmployees(['status' => 'Active']);
            $salaryStructures = $this->salaryService->getSalaryStructures();
            return view('salaries.generate.index', compact('employees', 'salaryStructures'));
        } catch (\Exception $e) {
            Log::error("Erreur lors du chargement des employés ou structures salariales: " . $e->getMessage());
            return redirect()->route('salaries.generate.index')->with('error', 'Erreur lors du chargement des données: ' . $e->getMessage());
        }
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'employe_id' => 'required|string',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'salary_structure' => 'required|string',
            'salaire_base' => $request->input('moyenne_salaire') == '1' ? 'nullable' : 'nullable|numeric|min:0'
        ]);

        try {
            $dateDebut = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['date_debut'])->format('d/m/Y');
            $dateFin = \Carbon\Carbon::createFromFormat('Y-m-d', $validated['date_fin'])->format('d/m/Y');
            
            $moyenneSalaire = $request->boolean('moyenne_salaire') ? '1' : '0';
            $ecraserSalaire = $request->boolean('ecraser_salaire') ? '1' : '0';
            
            $salaireBase = null;
            if ($moyenneSalaire == '1') {
                $salaireBase = $this->salaryService->getAverageSalary();
            } elseif (isset($validated['salaire_base']) && is_numeric($validated['salaire_base'])) {
                $salaireBase = floatval($validated['salaire_base']);
            }
            
            $result = $this->salaryService->generateMissingPayrolls(
                $validated['employe_id'],
                $dateDebut,
                $dateFin,
                $salaireBase,
                $validated['salary_structure'],
                $ecraserSalaire
            );

            if (!empty($result['errors'])) {
                return redirect()->route('salaries.generate.index')->with('error', implode(', ', $result['errors']));
            }

            return redirect()->route('salaries.generate.index')->with('success', "Génération terminée : {$result['success']} salaires créés, {$result['skipped']} mois ignorés.");
        } catch (\Exception $e) {
            Log::error("Erreur lors de la génération des salaires: " . $e->getMessage());
            return redirect()->route('salaries.generate.index')->with('error', 'Erreur lors de la génération des salaires: ' . $e->getMessage());
        }
    }
}