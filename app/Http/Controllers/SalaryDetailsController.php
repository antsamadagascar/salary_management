<?php

namespace App\Http\Controllers;

use App\Services\payroll\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Exception;

class SalaryDetailsController extends Controller
{
    private PayrollService $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function getSalaryDetails(Request $request)
    {
        try {
            $currentMonth = $request->get('month', Carbon::now()->format('Y-m'));
            $availableMonths = $this->payrollService->getAvailableMonths();
            
            if (empty($availableMonths)) {
                $availableMonths = [[
                    'value' => $currentMonth,
                    'label' => Carbon::createFromFormat('Y-m', $currentMonth)->format('F Y')
                ]];
            }
            if($currentMonth!=null) {
                $payrollData = $this->payrollService->getPayrollDataByMonth($currentMonth);
            }
            else {
                $payrollData = [];
            }

            $totals = $this->payrollService->getPayrollTotals($currentMonth);

            return view('payroll.details.salary-details', compact(
                'payrollData',
                'totals',
                'availableMonths',
                'currentMonth'
            ));
        } catch (Exception $e) {
            return view('payroll.details.salary-details', [
                'payrollData' => [],
                'totals' => [
                    'total_employees' => 0,
                    'total_gross_pay' => 0,
                    'total_deductions' => 0,
                    'total_net_pay' => 0,
                    'earnings_breakdown' => [],
                    'deductions_breakdown' => []
                ],
                'availableMonths' => [],
                'currentMonth' => Carbon::now()->format('Y-m'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * API pour récupérer les données de paie par AJAX
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPayrollData(Request $request): JsonResponse
    {
        try {
            $month = $request->get('month', Carbon::now()->format('Y-m'));
            
            $payrollData = $this->payrollService->getPayrollDataByMonth($month);
            $totals = $this->payrollService->getPayrollTotals($month);

            return response()->json([
                'success' => true,
                'data' => $payrollData,
                'totals' => $totals
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'totals' => []
            ], 500);
        }
    }

    /**
     * Exporte les données de paie en CSV
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportCsv(Request $request)
    {
        try {
            $month = $request->get('month', Carbon::now()->format('Y-m'));
            $payrollData = $this->payrollService->getPayrollDataByMonth($month);
            
            $monthLabel = Carbon::createFromFormat('Y-m', $month)->format('F_Y');
            $filename = "payroll_{$monthLabel}.csv";

            return response()->streamDownload(function () use ($payrollData) {
                $handle = fopen('php://output', 'w');
                
                // En-têtes CSV
                fputcsv($handle, [
                    'ID Employé',
                    'Nom Employé',
                    'Département',
                    'Poste',
                    'Salaire Brut',
                    'Total Déductions',
                    'Salaire Net',
                    'Détail Gains',
                    'Détail Déductions',
                    'Currency'
                ]);

                // Données
                foreach ($payrollData as $employee) {
                    $earningsDetail = collect($employee['earnings'])
                        ->map(fn($e) => $e['component'] . ': ' . number_format($e['amount'], 2))
                        ->implode(' | ');
                    
                    $deductionsDetail = collect($employee['deductions'])
                        ->map(fn($d) => $d['component'] . ': ' . number_format($d['amount'], 2))
                        ->implode(' | ');

                    fputcsv($handle, [
                        $employee['employee_id'],
                        $employee['employee_name'],
                        $employee['department'],
                        $employee['designation'],
                        number_format($employee['gross_pay'], 2),
                        number_format($employee['total_deduction'], 2),
                        number_format($employee['net_pay'], 2),
                        $earningsDetail,
                        $deductionsDetail
                    ]);
                }

                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Erreur lors de l\'export : ' . $e->getMessage());
        }
    }
}