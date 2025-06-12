<?php

namespace App\Http\Controllers;

use App\Services\employee\EmployeeService;
use App\Services\SalaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use Carbon\Carbon;

class GenerateSalaryController extends Controller
{
    protected $employeeService;
    protected $salaryService;

    public function __construct(EmployeeService $employeeService, SalaryService $salaryService)
    {
        $this->employeeService = $employeeService;
        $this->salaryService = $salaryService;
    }

    /**
     * Affiche le formulaire de génération des salaires
     */
    public function index()
    {
        try {
            $employees = $this->employeeService->getEmployees(['status' => 'Active']);
            return view('salaries.generate', compact('employees'));
        } catch (Exception $e) {
            return back()->withError('Erreur lors du chargement des employés: ' . $e->getMessage());
        }
    }

    /**
     * Génère les salaires manquants pour un employé
     */
    public function generate(Request $request)
    {
        $request->validate([
            'employe_id' => 'required|string',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'salaire_base' => 'required|numeric|min:0'
        ]);

        try {
            $generatedSalaries = $this->generateSalariesForEmployee(
                $request->employe_id,
                $request->date_debut,
                $request->date_fin,
                $request->salaire_base
            );

            $message = count($generatedSalaries) . ' salaire(s) généré(s) avec succès';
            
            // Si c'est une requête AJAX, retourner JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $generatedSalaries
                ]);
            }
            
            // Sinon, rediriger avec message de succès
            return back()->with('success', $message);

        } catch (Exception $e) {
            $errorMessage = 'Erreur lors de la génération: ' . $e->getMessage();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            }
            
            return back()->withError($errorMessage)->withInput();
        }
    }

    /**
     * Logique principale de génération des salaires
     */
    private function generateSalariesForEmployee(string $employeId, string $dateDebut, string $dateFin, float $salaireBase): array
    {
        $generatedSalaries = [];
        $dateStart = Carbon::parse($dateDebut)->startOfMonth();
        $dateEnd = Carbon::parse($dateFin)->startOfMonth();

        // Récupérer les salaires existants pour cet employé
        $existingSalaries = $this->salaryService->getSalariesByEmployee($employeId, $dateStart, $dateEnd);
        $existingMonths = $this->extractExistingMonths($existingSalaries);

        // Chercher le dernier salaire avant la date de début
        $lastSalaryAmount = $this->getLastSalaryAmount($employeId, $dateStart, $salaireBase);

        // Générer les salaires pour chaque mois manquant
        $currentMonth = $dateStart->copy();
        while ($currentMonth->lte($dateEnd)) {
            $monthKey = $currentMonth->format('Y-m');
            
            if (!in_array($monthKey, $existingMonths)) {
                $newSalary = $this->createSalaryForMonth($employeId, $currentMonth, $lastSalaryAmount);
                $generatedSalaries[] = $newSalary;
            }
            
            $currentMonth->addMonth();
        }

        return $generatedSalaries;
    }

    /**
     * Extrait les mois existants des salaires (mise à jour des champs)
     */
    private function extractExistingMonths(array $salaries): array
    {
        return array_map(function($salary) {
            return Carbon::parse($salary['from_date'])->format('Y-m');
        }, $salaries);
    }

    /**
     * Récupère le montant du dernier salaire ou utilise le salaire de base
     */
    private function getLastSalaryAmount(string $employeId, Carbon $dateStart, float $salaireBase): float
    {
        try {
            $lastSalary = $this->salaryService->getLastSalaryBefore($employeId, $dateStart);
            // Dans ERPNext, le montant peut être dans 'base' ou il faut le calculer
            return $lastSalary ? ($lastSalary['base'] ?? $salaireBase) : $salaireBase;
        } catch (Exception $e) {
            return $salaireBase;
        }
    }

    /**
     * Crée un nouveau salaire pour un mois donné
     */
    private function createSalaryForMonth(string $employeId, Carbon $month, float $amount): array
    {
        // Récupérer une structure de salaire par défaut
        $salaryStructure = $this->salaryService->getDefaultSalaryStructure();
        
        if (!$salaryStructure) {
            throw new Exception("Aucune structure de salaire active trouvée. Veuillez créer une structure de salaire dans ERPNext.");
        }
        
        $salaryData = [
            'employee' => $employeId,
            'from_date' => $month->format('Y-m-d'),
            'to_date' => $month->copy()->endOfMonth()->format('Y-m-d'),
            'salary_structure' => $salaryStructure,
            'base' => $amount,
            'docstatus' => 0  // Draft
        ];

        return $this->salaryService->createSalary($salaryData);
    }
}