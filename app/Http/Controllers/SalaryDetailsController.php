<?php

namespace App\Http\Controllers;

use App\Services\payroll\PayrollDataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Exception;

class SalaryDetailsController extends Controller
{
    private PayrollDataService $payrollService;

    public function __construct(PayrollDataService $payrollService)
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
    
}