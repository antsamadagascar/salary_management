<?php
namespace App\Http\Controllers;

use App\Services\config\ConfigSalaryService;
use App\Services\filter\FilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FilterSalaryController extends Controller {

    private ConfigSalaryService $configSalary;
    private FilterService $filterService;

    public function __construct(ConfigSalaryService $configSalary, FilterService $filterService) {
        $this->configSalary = $configSalary;
        $this->filterService = $filterService;
    }

    public function index() {
        $salaryComponents = $this->configSalary->getSalaryComponents();
        $conditions = [
            ['name' => 'inferieur'],
            ['name' => 'superieur'],
            ['name' => 'egal'],
            ['name' => 'inferieur_egal'],
            ['name' => 'superieur_egal']
        ];

        return view('filter.salary.index', compact('salaryComponents', 'conditions'));
    }

    public function getSalaryFilter(Request $request) {
        $validated = $request->validate([
            'conditions' => 'required|string',
            'salaire_base' => 'required|numeric|min:0',
            'salaryComponents' => 'required|string'
        ]);

        try {
            $results = $this->filterService->getSalaryByCriteria($validated);

            $conditions = [
                ['name' => 'inferieur'],
                ['name' => 'superieur'],
                ['name' => 'egal'],
                ['name' => 'inferieur_egal'],
                ['name' => 'superieur_egal']
            ];

            return view('filter.salary.index', [
                'salaryComponents' => $this->configSalary->getSalaryComponents(),
                'conditions' => $conditions,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des salaires : " . $e->getMessage());
            return redirect()->route('filter.salary.index')->with('error', 'Erreur chargement des données : ' . $e->getMessage());
        }
    }

}
