<?php
namespace App\Http\Controllers;
use App\Services\employee\EmployeeService;
use App\Services\api\ErpApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    private EmployeeService $employeeService;
    private ErpApiService $erpApiService;

    public function __construct(EmployeeService $employeeService,ErpApiService $erpApiService)
    {
        $this->employeeService = $employeeService;
        $this->erpApiService = $erpApiService;
    }

    public function index(Request $request)
    {
        return view('dashboard.index');
    }

    // MÉTHODE FORMULAIRE
    public function formulaire()
    {
        $departments = [
            ['id' => 'RH', 'name' => 'Ressources Humaines'],
            ['id' => 'IT', 'name' => 'Informatique'],
            ['id' => 'Finance', 'name' => 'Finance']
        ];
        
        $managers = $this->employeeService->getEmployees(['filters' => [['is_manager', '=', 1]]]);
        
        return view('formulaire.index', compact('departments', 'managers'));
    }

    // MÉTHODE TABLEAU
    public function tableau(Request $request)
    {
        // Récupére des données depuis API
        $search = $request->get('search');
        $filters = [];
        
        if ($search) {
            $filters[] = ['employee_name', 'like', '%' . $search . '%'];
        }
        
        $employees = $this->employeeService->getEmployees(['filters' => $filters]);
        
        return view('tableau.index', compact('employees', 'search'));
    }

    // MÉTHODE STORE
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_name' => 'required|string|max:255',
            'email' => 'required|email',
            'salary' => 'nullable|numeric|min:0',
            'date_embauche' => 'nullable|date',
            'department' => 'nullable|string'
        ]);

        try {
            $result = $this->employeeService->createEmployee($validated);
            return redirect()->back()->with('success', 'Employé créé avec succès');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }
}