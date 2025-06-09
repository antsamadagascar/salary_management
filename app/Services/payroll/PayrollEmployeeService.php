<?php

namespace App\Services\payroll;

use App\Services\api\ErpApiService;
use App\Services\employee\EmployeeService;
use App\Services\payroll\PayrollDataService;
use Carbon\Carbon;
use Exception;

class PayrollEmployeeService
{
    private ErpApiService $erpApiService;
    private EmployeeService $employeeService;
    private PayrollDataService $payrollDataService;

    public function __construct(ErpApiService $erpApiService,EmployeeService $employeeService,PayrollDataService $payrollDataService)
    {
        $this->erpApiService = $erpApiService;
        $this->employeeService = $employeeService;
        $this->payrollDataService = $payrollDataService;
    }

    /**
     * Récupére les salaires d'un employé par mois
     */
    public function getEmployeeSalariesByMonth(string $employeeId): array
    {
        try {
            $salarySlips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => [
                    ['employee', '=', $employeeId]
                ],
                'fields' => [
                    'name', 'employee', 'employee_name', 'posting_date', 
                    'start_date', 'end_date', 'gross_pay', 'total_deduction', 
                    'net_pay', 'status'
                ],
                'order_by' => 'posting_date desc',
                'limit_page_length' => 1000
            ]);

            // Grouper par mois
            $salariesByMonth = [];
            foreach ($salarySlips as $slip) {
                $month = Carbon::parse($slip['posting_date'])->format('Y-m');
                $monthName = Carbon::parse($slip['posting_date'])->locale('fr')->translatedFormat('F Y');
                
                if (!isset($salariesByMonth[$month])) {
                    $salariesByMonth[$month] = [
                        'month' => $month,
                        'month_name' => $monthName,
                        'slips' => []
                    ];
                }
                
                $salariesByMonth[$month]['slips'][] = $slip;
            }

            return array_values($salariesByMonth);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des salaires: " . $e->getMessage());
        }
    }

    /**
     * Récupére les fiches de paie pour un mois donné
     */
    public function getSalarySlipsForMonth(string $employeeId, string $month): array
    {
        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->format('Y-m-d');
            $salarySlips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => [
                    ['employee', '=', $employeeId],
                    ['posting_date', '>=', $startDate],
                    ['posting_date', '<=', $endDate]
                ],
                'fields' => [
                    'name', 'employee', 'employee_name', 'posting_date',
                    'start_date', 'end_date', 'gross_pay', 'total_deduction',
                    'net_pay', 'status', 'currency'
                ]
            ]);
    
            $detailedSalarySlips = [];
            foreach ($salarySlips as $slip) {
                $detailedSlip = $this->payrollDataService->getSalarySlip($slip['name']);
                if ($detailedSlip) {
                    $detailedSalarySlips[] = $detailedSlip;
                } else {
                    $detailedSalarySlips[] = $slip;
                }
            }
    
            return $detailedSalarySlips;
            
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des fiches de paie: " . $e->getMessage());
        }
    }

    /**
     * Calcul les statistiques de paie pour un employé
     */
    public function getPayrollStats(string $employeeId): array
    {
        try {
            $salaries = $this->getEmployeeSalariesByMonth($employeeId);
            
            $totalNetPay = 0;
            $totalGrossPay = 0;
            $totalDeductions = 0;
            $monthsCount = 0;

            foreach ($salaries as $monthData) {
                foreach ($monthData['slips'] as $slip) {
                    $totalNetPay += floatval($slip['net_pay'] ?? 0);
                    $totalGrossPay += floatval($slip['gross_pay'] ?? 0);
                    $totalDeductions += floatval($slip['total_deduction'] ?? 0);
                    $monthsCount++;
                }
            }

            return [
                'total_net_pay' => $totalNetPay,
                'total_gross_pay' => $totalGrossPay,
                'total_deductions' => $totalDeductions,
                'average_net_pay' => $monthsCount > 0 ? $totalNetPay / $monthsCount : 0,
                'months_count' => $monthsCount
            ];
        } catch (Exception $e) {
            return [
                'total_net_pay' => 0,
                'total_gross_pay' => 0,
                'total_deductions' => 0,
                'average_net_pay' => 0,
                'months_count' => 0
            ];
        }
    }
}