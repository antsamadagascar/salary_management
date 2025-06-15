<?php

namespace App\Http\Controllers;

use App\Services\employee\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class EmployeeController extends Controller
{
    private EmployeeService $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    public function index(Request $request)
    {
        try {
            $filters = $this->getFiltersFromRequest($request);
            $employees = $this->employeeService->getEmployees($filters);
            $departments = $this->employeeService->getDepartments();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'employees' => $employees,
                    'total' => count($employees),
                    'html' => view('employees.partials.employee-list', compact('employees'))->render()
                ]);
            }

            return view('employees.index', compact('employees', 'departments', 'filters'));
        } catch (Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            return back()->with('error', $e->getMessage());
        }
    }


    public function search(Request $request): JsonResponse
    {
        try {
            $filters = $this->getFiltersFromRequest($request);
            $employees = $this->employeeService->getEmployees($filters);

            return response()->json([
                'success' => true,
                'employees' => $employees,
                'total' => count($employees),
                'html' => view('employees.partials.employee-list', compact('employees'))->render()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function stats(): JsonResponse
    {
        try {
            $stats = $this->employeeService->getEmployeeStats();
            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function show(string $name)
    {
        try {
            $employee = $this->employeeService->getEmployeeByName($name);
            
            if (!$employee) {
                return redirect()->route('employees.index')->with('error', 'Employé non trouvé');
            }

            return view('employees.show', compact('employee'));
        } catch (Exception $e) {
            return redirect()->route('employees.index')->with('error', $e->getMessage());
        }
    }

    public function create()
    {
        try {
            $departments = $this->employeeService->getDepartments();
            return view('employees.create', compact('departments'));
        } catch (Exception $e) {
            return redirect()->route('employees.index')->with('error', $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'employee_number' => 'required|string|max:255',
            'department' => 'required|string',
            'designation' => 'required|string|max:255',
            'date_of_joining' => 'required|date',
            'gender' => 'required|in:Male,Female,Other',
            'personal_email' => 'nullable|email',
            'cell_number' => 'nullable|string|max:20'
        ]);

        try {
            $data = $request->only([
                'employee_name', 'first_name', 'last_name', 'employee_number',
                'department', 'designation', 'date_of_joining', 'gender',
                'personal_email', 'cell_number'
            ]);

            $success = $this->employeeService->createEmployee($data);

            if ($success) {
                return redirect()->route('employees.index')->with('success', 'Employé créé avec succès');
            }

            return back()->with('error', 'Erreur lors de la création de l\'employé')->withInput();
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }


    public function edit(string $name)
    {
        try {
            $employee = $this->employeeService->getEmployeeByName($name);
            $departments = $this->employeeService->getDepartments();
            
            if (!$employee) {
                return redirect()->route('employees.index')->with('error', 'Employé non trouvé');
            }

            return view('employees.edit', compact('employee', 'departments'));
        } catch (Exception $e) {
            return redirect()->route('employees.index')->with('error', $e->getMessage());
        }
    }

 
    public function update(Request $request, string $name)
    {
        $request->validate([
            'employee_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'department' => 'required|string',
            'designation' => 'required|string|max:255',
            'personal_email' => 'nullable|email',
            'cell_number' => 'nullable|string|max:20'
        ]);

        try {
            $data = $request->only([
                'employee_name', 'first_name', 'last_name',
                'department', 'designation', 'personal_email', 'cell_number'
            ]);

            $success = $this->employeeService->updateEmployee($name, $data);

            if ($success) {
                return redirect()->route('employees.index')->with('success', 'Employé mis à jour avec succès');
            }

            return back()->with('error', 'Erreur lors de la mise à jour de l\'employé')->withInput();
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }


    public function destroy(string $name)
    {
        try {
            $success = $this->employeeService->deleteEmployee($name);

            if ($success) {
                return redirect()->route('employees.index')->with('success', 'Employé supprimé avec succès');
            }

            return back()->with('error', 'Erreur lors de la suppression de l\'employé');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function getFiltersFromRequest(Request $request): array
    {
        return [
            'search' => $request->get('search', ''),
            'department' => $request->get('department', ''),
            'designation' => $request->get('designation', ''),
            'gender' => $request->get('gender', ''),
            'status' => $request->get('status', 'Active'),
            'limit' => $request->get('limit', 20),
            'page' => $request->get('page', 1),
            'order_by' => $request->get('order_by', 'employee_name asc')
        ];
    }
}