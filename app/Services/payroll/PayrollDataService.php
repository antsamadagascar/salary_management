<?php

namespace App\Services\payroll;

use App\Services\api\ErpApiService;
use App\Services\employee\EmployeeService;
use Carbon\Carbon;
use Exception;

class PayrollDataService
{
    private ErpApiService $erpApiService;
    private EmployeeService $employeeService;

    public function __construct(ErpApiService $erpApiService,EmployeeService $employeeService)
    {
        $this->erpApiService = $erpApiService;
        $this->employeeService = $employeeService;
    }


    /**
     * Récupére une fiche de paie spécifique
     */
    public function getSalarySlip(string $salarySlipId): ?array
    {
        try {
            $salarySlip = $this->erpApiService->getResourceByName('Salary Slip', $salarySlipId, [
                'params' => ['include_child_documents' => 'true']
            ]);

            if ($salarySlip) {
                $salarySlip['employee_details'] = $this->employeeService->getEmployeeByID($salarySlip['employee']);
            }

            return $salarySlip;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Récupère les données de paie pour un mois donné
     *
     * @param string $month Format: Y-m (ex: 2024-01)
     * @return array
     */
    public function getPayrollDataByMonth(string $month): array
    {
        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->format('Y-m-d');

            // Récupére les fiches de paie pour le mois
            $payslips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => [
                    ['start_date', '>=', $startDate],
                    ['end_date', '<=', $endDate],
                    ['docstatus', '=', 1] // Seulement les fiches validées
                ],
                'fields' => [
                    'name',
                    'employee',
                    'employee_name',
                    'department',
                    'designation',
                    'start_date',
                    'end_date',
                    'gross_pay',
                    'net_pay',
                    'total_deduction',
                    'currency'
                ]
            ]);

            $payrollData = [];

            foreach ($payslips as $payslip) {
                $employeeData = [
                    'employee_id' => $payslip['employee'],
                    'employee_name' => $payslip['employee_name'],
                    'department' => $payslip['department'] ?? 'N/A',
                    'designation' => $payslip['designation'] ?? 'N/A',
                    'gross_pay' => $payslip['gross_pay'] ?? 0,
                    'total_deduction' => $payslip['total_deduction'] ?? 0,
                    'currency' => $payslip['currency'] ?? MGA,
                    'net_pay' => $payslip['net_pay'] ?? 0,
                    'earnings' => [],
                    'deductions' => []
                ];

                // Récupére les détails des gains et déductions
                $employeeData['earnings'] = $this->getPayrollComponents($payslip['name'], 'earnings');
                $employeeData['deductions'] = $this->getPayrollComponents($payslip['name'], 'deductions');

                $payrollData[] = $employeeData;
            }

            return $payrollData;
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des données de paie : " . $e->getMessage());
        }
    }

    /**
     * Récupère les composants de paie (gains ou déductions)
     *
     * @param string $payslipName
     * @param string $type 'earnings' ou 'deductions'
     * @return array
     */
    private function getPayrollComponents(string $payslipName, string $type): array
    {
        try {
            $components = $this->erpApiService->getResource('Salary Detail', [
                'filters' => [
                    ['parent', '=', $payslipName],
                    ['parentfield', '=', $type]
                ],
                'fields' => [
                    'salary_component',
                    'amount',
                    'default_amount'
                ]
            ]);

            return array_map(function ($component) {
                return [
                    'component' => $component['salary_component'],
                    'amount' => $component['amount'] ?? $component['default_amount'] ?? 0
                ];
            }, $components);
        } catch (Exception $e) {
            return $this->getPayrollComponentsAlternative($payslipName, $type);
        }
    }

    /**
     * Méthode alternative pour récupérer les composants via l'API spécifique du document
     */
    private function getPayrollComponentsAlternative(string $payslipName, string $type): array
    {
        try {
            $payslip = $this->erpApiService->getResourceByName('Salary Slip', $payslipName);
            
            if (!$payslip || !isset($payslip[$type])) {
                return [];
            }

            return array_map(function ($component) {
                return [
                    'component' => $component['salary_component'] ?? 'Unknown',
                    'amount' => $component['amount'] ?? $component['default_amount'] ?? 0
                ];
            }, $payslip[$type]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Récupère les totaux pour un mois donné
     *
     * @param string $month
     * @return array
     */
    public function getPayrollTotals(string $month): array
    {
        $payrollData = $this->getPayrollDataByMonth($month);
        
        $totals = [
            'total_employees' => count($payrollData),
            'total_gross_pay' => 0,
            'total_deductions' => 0,
            'total_net_pay' => 0,
            'earnings_breakdown' => [],
            'deductions_breakdown' => []
        ];

        foreach ($payrollData as $employee) {
            $totals['total_gross_pay'] += $employee['gross_pay'];
            $totals['total_deductions'] += $employee['total_deduction'];
            $totals['total_net_pay'] += $employee['net_pay'];

            // Agrége les gains par composant
            foreach ($employee['earnings'] as $earning) {
                $component = $earning['component'];
                if (!isset($totals['earnings_breakdown'][$component])) {
                    $totals['earnings_breakdown'][$component] = 0;
                }
                $totals['earnings_breakdown'][$component] += $earning['amount'];
            }

            // Agrége les déductions par composant
            foreach ($employee['deductions'] as $deduction) {
                $component = $deduction['component'];
                if (!isset($totals['deductions_breakdown'][$component])) {
                    $totals['deductions_breakdown'][$component] = 0;
                }
                $totals['deductions_breakdown'][$component] += $deduction['amount'];
            }
        }

        return $totals;
    }

    /**
     * Récupère la liste des mois disponibles
     *
     * @return array
     */
    public function getAvailableMonths(): array
    {
        try {
            $payslips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => [
                    ['docstatus', '=', 1]
                ],
                'fields' => ['start_date', 'end_date'],
                'order_by' => 'start_date asc',
                'limit' => 1000 // Limite pour éviter trop de données
            ]);

            $months = [];
            foreach ($payslips as $payslip) {
                $month = Carbon::parse($payslip['start_date'])->format('Y-m');
                $monthLabel = Carbon::parse($payslip['start_date'])->format('F Y');
                
                if (!in_array($month, array_column($months, 'value'))) {
                    $months[] = [
                        'value' => $month,
                        'label' => $monthLabel
                    ];
                }
            }

            // Si aucun mois trouvé, ajouter le mois courant
            if (empty($months)) {
                $currentMonth = Carbon::now()->format('Y-m');
                $months[] = [
                    'value' => $currentMonth,
                    'label' => Carbon::now()->format('F Y')
                ];
            }

            return $months;
        } catch (Exception $e) {
            // Retourne au moins le mois courant en cas d'erreur
            $currentMonth = Carbon::now()->format('Y-m');
            return [[
                'value' => $currentMonth,
                'label' => Carbon::now()->format('F Y')
            ]];
        }
    }
}