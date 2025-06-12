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
            Log::error('Error fetching employees: ' . $e->getMessage());
            return back()->with('error', 'Unable to fetch employees.');
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_name' => 'required|string',
            'salary' => 'required|numeric|min:0',
            'date_debut' => 'required|date_format:Y-m',
            'date_fin' => 'required|date_format:Y-m|after_or_equal:date_debut'
        ]);

        try {
            $startDate = Carbon::createFromFormat('Y-m', $validated['date_debut'])->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $validated['date_fin'])->endOfMonth();
            $employee = $this->employeeService->getEmployeeByName($validated['employee_name']);

            if (!$employee) {
                return back()->with('error', 'Employee not found.');
            }

            $existingSalary = $this->employeeService->getSalarySlip($validated['employee_name'], $startDate->format('Y-m-d'));
            $baseSalary = $existingSalary ? ($existingSalary['gross_pay'] ?? $validated['salary']) : $validated['salary'];

            $currentDate = $startDate->copy();
            $results = [];
            while ($currentDate <= $endDate) {
                $existingSlip = $this->employeeService->getSalarySlip($validated['employee_name'], $currentDate->format('Y-m-d'));
                if (!$existingSlip) {
                    $result = $this->employeeService->generateSalarySlip(
                        $validated['employee_name'],
                        $baseSalary,
                        $currentDate->format('Y-m-d')
                    );
                    $results[] = $result;
                }
                $currentDate->addMonth();
            }

            if (in_array(false, $results, true)) {
                return back()->with('error', 'Some salaries failed to generate.');
            }

            return redirect()->route('generate.tableau')->with('success', 'Salaries generated successfully.');
        } catch (\Exception $e) {
            Log::error('Error generating salaries: ' . $e->getMessage());
            return back()->with('error', 'Error generating salaries: ' . $e->getMessage());
        }
    }
}