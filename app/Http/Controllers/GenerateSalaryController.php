<?php

namespace App\Http\Controllers;

use App\Services\employee\EmployeeService;

class GenerateSalaryController extends Controller
{
    protected $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    public function index()
    {
        try {
            $employees = $this->employeeService->getEmployees(['status' => 'Active']);
            return view('salaries.generate.index', compact('employees'));
        } catch (Exception $e) {
            return back()->withError('Erreur lors du chargement des employés: ' . $e->getMessage());
        }
    }
}